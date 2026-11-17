# 升级到 2.0.1

当前源码定为 2.0.1，尚未发布 Composer 包或创建 Git 标签。版本详情见 [CHANGELOG.md](CHANGELOG.md)，完整 API 见 [MANUAL.md](MANUAL.md)。

## 从 2.0.0 升级

2.0.1 修复两项回归：unary 响应不再受入站 16 MiB 限额约束；二进制异常保留普通安全 gRPC 状态头，避免空 body 经 Nginx grpc_pass 后丢失 trailers 状态。入站线上/解压限额、流式默认限额、协议及服务接口保持不变；`send` 的新增参数为可选，默认行为不变。接口生成器仍为 2.0.0，无协议变更时无需重新生成。

发布后通过 Composer 升级到 `hetao29/php-grpc-server-protobuf:2.0.1`，同步锁文件、vendor 和实际运行进程；验证正常/大体积 unary、超限入站以及经代理的二进制错误头/trailers。只修改版本声明不代表运行进程已切换。

## 从 1.x 升级

2.0 系列统一使用 Swoole Request/Response，增加协程客户端及三类流式 RPC，包含以下不兼容入口调整。

### 1. 同步运行环境和接口生成器

服务端与 Swoole 客户端必须加载包含 `http2_stream_mode`、`Request::recv()` 和客户端增量 HTTP/2 接收支持的扩展。本地示例说明了如何单独加载已构建扩展；不能只替换 PHP 类文件后继续使用旧传输扩展。

配套使用 `php-grpc-server-protobuf-plugin 2.0.0`，重新生成接口。普通 unary 的参数/返回类型保持消息类，服务端流返回 `iterable`，客户端流参数改为 `\GRpcStream`，双向流同时采用两种变化。同步调整实现；不手改生成接口。官方 `grpc_php_plugin` 仍负责生成原生客户端，与此接口插件分开安装。

### 2. 改用 Composer 加载

2.0.1 发布后，将消费项目约束改为 `hetao29/php-grpc-server-protobuf:2.0.1`，通过 Composer 安装。

移除手写 `spl_autoload_register`、ClassLoader 配置和逐类 require，在业务项目的 Composer `autoload.psr-4` 中声明服务及生成代码，然后运行 `composer dump-autoload`。入口只加载一次 `vendor/autoload.php`；库自身的全局类型由 `autoload.classmap` 按需加载。

仓库示例当前声明和锁文件固定依赖已发布的 2.0.0；2.0.1 发布后通过 Composer 同步升级。库类型由依赖包自身的 Composer 配置加载；示例只配置应用命名空间及公共函数，不配置指向根源码的 classmap。不要在同一进程混载两版全局 `GRpcServer`。

### 3. 替换服务入口

旧入口返回编码后的字符串：

```php
// 1.x，升级后移除此调用。
$body = GRpcServer::run($uri, $data, $contentType, $encoding, $token);
```

新入口负责读取请求和写完响应：

```php
GRpcServer::serve($request, $response, $token);
```

不再把 URI、body、类型和 encoding 放在调用参数中；这些信息来自当前 Swoole 请求。构造参数从第三个参数开始，原顺序与值保留。调用后不要再 `echo` 返回值或重复发送成功 trailers / `end()`。

事件式 Server 可直接注册 `[GRpcServer::class, 'serve']`，协程式 Server 注册到 `handle('/')`。显式开启 HTTP/2 和流式模式，配置示例见 [samples/server/server.php](samples/server/server.php)。JSON 与二进制使用同一个回调，不再自行收集 JSON body。

`run()`、`getRawData()` 及 `samples/www/index.php` 已删除；PHP-FPM 调度入口退役。`nginx/gateway.conf` 是旧 FPM 配置，不能用作 2.x 部署入口。认证或 AES 等适配逻辑不能直接丢弃；迁移时应保留在应用适配边界，核对输入与响应协议，不把密文直接传给新调度器。

### 4. 核对响应与方法约束

`serve()` 直接完成响应并捕获 `Throwable`。不要继续依赖旧 `run()` 的异常包装 code `-1`～`-4` 或字符串返回值。JSON 失败为 HTTP 500 和固定状态 13；二进制失败以最终 trailers 返回状态 13，并在响应头尚未发送时同时设置普通 grpc-status/grpc-message，保留空 body 经 Nginx grpc_pass 的错误状态；已发送数据后的失败继续以最终 trailers 为准。原有成功时的最终状态为 0。

服务方法必须是公共实例方法，第一个参数是具体类类型；静态、私有、受保护及无类型参数的方法不再可调度。服务类仍须实现对应后缀的接口。接口后缀设置在启动时完成，默认仍为 `Interface`。早期接入中若有 `setServiceNamespaces()`，移除该旧配置；路由按当前服务类和接口约定解析。

### 5. 核对客户端结束与限制

Swoole 客户端统一使用 `send / recv / closeSend / close`；在 `finally` 中释放连接。单条请求发送后调用 `closeSend()`，双向流可在半关闭前交替发送和接收。必须读到成功的最终状态，收到消息本身不能判定 RPC 成功。

二进制入站消息保留 16 MiB 的线上及解压大小限制；服务端 unary 返回保留旧的不设 16 MiB 出站上限的合同，消费端自行配置接收限额；流式回复和客户端发送继续按默认限额检查，JSON HTTP 入口统一为 2 MiB 请求上限。JSON 与整段 body AES 不提供流式协议。注意 Swoole 的收包限制独立于库的每消息限制，按示例配置并核对实际流量。

使用 [samples 手册](samples/README.md) 分别验证 unary、服务端流、客户端流、双向流和 JSON；原生客户端也应核对最终 gRPC 状态。此升级文档不要求提交、打标签或重启业务服务。
