# php-grpc-server-protobuf 2.0.1 使用手册

本手册对应 `hetao29/php-grpc-server-protobuf` 的 **2.0.1 待发布源码**，配套接口生成器为 `php-grpc-server-protobuf-plugin` **2.0.0**。完整启动命令和预期输出见 [samples/README.md](samples/README.md)；从 1.x 迁移见 [UPGRADE.md](UPGRADE.md)。

## 环境与加载

验证环境为 PHP 8.4.27，服务端使用包含本次流式补丁的 Swoole。需要 `http2_stream_mode` 和 `Swoole\Http\Request::recv()`；客户端还使用 HTTP/2 pipeline 增量接收。仅检查普通 Swoole 版本号不能证明具备这些能力。

2.0.1 发布后可用 `composer require 'hetao29/php-grpc-server-protobuf:2.0.1'` 安装；当前版本声明不代表包或 Git 标签已发布。包的现有依赖声明包含 `ext-swoole`、`ext-grpc`、`google/protobuf` 和 `grpc/grpc`。仓库示例的声明和锁文件目前固定引用已发布的 2.0.0，不加载根目录的本地库源码；2.0.1 发布后须在 samples/libs 通过 Composer 同步升级声明、锁文件及 vendor，才能验证本次补丁；安装依赖时需要两个扩展，实际运行中只有原生客户端示例使用 grpc 扩展进行 RPC 传输。Protobuf C 扩展可选。

库在 `composer.json` 的 `autoload.classmap` 中声明四个全局类型：`GRpcServer`、`GRpcServerInterface`、`GRpcStream`、`GRpcClient`。业务项目在自己的 `autoload.psr-4` 中声明服务实现和生成代码；配置示例见 [README.md](README.md)。更新配置后运行 `composer dump-autoload`，入口仅加载 `vendor/autoload.php`，不逐个 require 类文件。

## 服务端入口

`GRpcServer::serve(Swoole\Http\Request $request, Swoole\Http\Response $response, ...$args): void` 是唯一调度入口。它读取当前请求、调用服务并结束响应。`$args` 原样传给服务构造方法。

事件式 Server 注册 `[GRpcServer::class, 'serve']` 到 `on('request', ...)`；协程式 Server 注册到 `handle('/', ...)`。配置与启动示例见 [samples/server/server.php](samples/server/server.php)。事件式 Server 须启用协程；HTTP/2 须开启 `open_http2_protocol` 和 `http2_stream_mode`，禁用 HTTP 响应压缩。示例将 `package_max_length` 设为二进制单消息上限加五字节帧头，避免默认收包限制先于应用限制拒绝边界请求；它与每消息大小限制不是同一项设置。

需要认证上下文时，用当前请求提取并显式传入：

```php
$server->handle('/', static function ($request, $response): void {
    $token = $request->header['authorization'] ?? '';
    GRpcServer::serve($request, $response, $token);
});
```

传入 token 不等于完成认证；服务或应用适配器继续负责验证身份、权限与对象归属。请求状态应留在当前请求、服务实例及局部变量中。

## 路由和服务约定

路径严格采用 `/Package.Service/Method`，例如 `/Test.Helloworld.Greeter/SayHello` 对应 `Test\Helloworld\Greeter::SayHello()`。不接受额外斜线、百分号转义或反斜线。

服务类必须实现同名加 `Interface` 后缀的接口。可在启动阶段调用 `GRpcServer::setInterfaceSuffix('Contract')` 改用其他非空 ASCII 标识符后缀；不要按请求更改这一进程配置。目标方法必须是公共实例方法，第一个参数必须有具体的类类型声明。公共辅助方法即使未出现在接口中仍可能被调度，不能视为私有入口。`NAME` 常量不作为访问控制依据。

## 四种 RPC 的签名

| Proto 输入 / 输出 | PHP 参数 | PHP 返回值 |
| --- | --- | --- |
| 单条 / 单条 | `Request $request` | `Reply` |
| 单条 / 流 | `Request $request` | `iterable`，元素为 Reply |
| 流 / 单条 | `GRpcStream $request` | `Reply` |
| 流 / 流 | `GRpcStream $request` | `iterable`，元素为 Reply |

接口由配套生成器读取 proto 的 stream 标志生成，不手改生成文件。示例在 `samples` 执行 `make proto`，统一使用 Docker Hub 的 `hetao29/docker-protoc:36.2.1` 镜像（protoc 36.2、接口插件 2.0.0），不调用宿主机的 protoc 或本地插件。具体服务实现见 [Greeter.php](samples/app/Test/Helloworld/Greeter.php)。`GRpcStream::recv(Request::class)` 每次读取一条消息，干净半关闭时返回 `null`；`send($reply)` 写一条消息，失败抛出异常。服务可用 `yield` 输出消息；直接 `send()` 的服务也可按自行声明的接口返回 `void`，不要改写生成器定义的返回契约。

客户端流必须读取到半关闭才能知道输入结束。双向流可以读取一条就回复一条，无需先收齐所有请求。单条输入必须恰好包含一个完整消息，空 body 或第二条消息均为错误；长度为零的 Protobuf 消息仍需五字节帧头。

## Swoole 客户端

在协程内创建一次调用：

```php
$call = new GRpcClient($host, $port, '/Test.Helloworld.Greeter/SayHello', HelloReply::class);
try {
    $call->send(new HelloRequest(['name' => 'Tony']));
    $call->closeSend();
    while (($reply = $call->recv()) !== null) {
        echo $reply->getMessage(), "\n";
    }
} finally {
    $call->close();
}
```

这些消息类型须先通过 Composer 加载，完整可运行脚本见 [hello.php](samples/client/hello.php)。

| API | 行为 |
| --- | --- |
| `new GRpcClient($host, $port, $path, $responseClass, $timeout = 5, $tls = false, $metadata = [])` | 建立本次调用；超时单位为秒，覆盖连接及整个 RPC；metadata 为 HTTP/2 线上 header 值，二进制元数据须先 base64 编码 |
| `send($message)` | 发送一条请求消息 |
| `closeSend()` | 半关闭请求，之后继续接收回复 |
| `recv()` | 返回一条回复；最终状态为 0 且输入结束时返回 `null`；传输、期限、解码或最终状态错误抛异常 |
| `close()` | 关闭调用并释放连接，未完成时取消本次调用 |

每次调用独占一个 HTTP/2 连接和接收协程，使用有界队列，无自动重试或连接池。同一调用允许一个应用读者和一个应用写者并发，不允许多个同时读或多个同时写。收到部分消息不代表成功，必须继续读到最终状态。原生 grpc 客户端的 timeout 选项使用微秒，与这里的秒不同。

## HTTP、错误和大小限制

| 情况 | 结果 |
| --- | --- |
| 非 POST | HTTP 405，`Allow: POST` |
| 不支持的 Content-Type | HTTP 415 |
| HTTP/1 请求二进制 gRPC | HTTP 426 |
| `application/json` | HTTP/1、HTTP/2 的 unary 请求，成功为 JSON 响应及 `grpc-status: 0` 普通响应头 |
| `application/grpc`、`application/grpc+proto` | HTTP/2 二进制 unary 或流式 RPC，成功以最终 `grpc-status: 0` trailers 结束 |
| 服务、路由或解码失败 | JSON 为 HTTP 500、状态 13 和固定错误体；二进制为最终状态 13 及 `Internal server error` |

二进制异常在尚未发送响应头时，除最终错误 trailers 外还设置普通 `grpc-status: 13` 和安全 `grpc-message`；Nginx 等代理可能因 `Content-Length: 0` 提前结束空 body 并丢弃 trailers，普通状态头保证客户端仍能读取失败。已发送数据时仍须读取最终 trailers，不以先收到消息判断成功。

`serve()` 捕获 `Throwable`，不透传业务异常文本；日志只记录异常类和 code。响应已经不可写时不再回写。已发出的流式消息不能撤回，因此客户端必须检查最终状态。

`MAX_MESSAGE_LENGTH` 为 16 MiB，分别限制单条二进制消息的线上 payload 和解压后的 payload，不含五字节帧头；整条流可以超过 16 MiB。压缩 flag 为 1 时只支持 `grpc-encoding: gzip`，并检查完整结束、尾随数据及解压大小；flag 为 0 不解压。`MAX_JSON_LENGTH` 为 2 MiB，仅限制 JSON 请求体；JSON 不支持流式调用。

接收与发送大小校验分开处理：服务端单请求、单响应的 unary 返回保留旧契约，不受 16 MiB 出站限制；请求线上/解压大小仍受限，流式 RPC 回复和客户端发送保留默认限额。`GRpcStream::send($message, $enforceSizeLimit = true)` 的第二参数为 false 时仅跳过本次发送检查，不改变接收限制或后续 send。消费端需单独配置可接受的响应大小。

`encode($message, $content_type = null)` 生成单条二进制帧，或在类型为 `'json'` 时生成 JSON。`decode($className, $body, $content_type = null, $grpc_encoding = null, $max_message_length = MAX_MESSAGE_LENGTH)` 进行对应单消息解码；最后一个参数必须为正数，仅用于二进制消息限制。`decode()` 的 JSON 分支不自行应用 HTTP 入口的 2 MiB 限制。

整段 body 的 AES、自定义内容变换、认证和 TLS 证书配置属于应用传输适配，不由此库实现。已废弃的 FPM `run()` 入口不能用于 2.x。
