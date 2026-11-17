# php-grpc-server-protobuf 2.0.0 示例手册

这里通过 Composer 安装官方 `hetao29/php-grpc-server-protobuf:2.0.0`，使用其中的 `GRpcServer / GRpcClient / GRpcStream`，并沿用 `Test.Helloworld.Greeter` 协议。三类流使用同一个服务端，客户端分别放在独立文件中。

配套接口生成器版本为 `php-grpc-server-protobuf-plugin 2.0.0`。库 API 见[使用手册](../MANUAL.md)，1.x 接入方式调整见[升级指南](../UPGRADE.md)，版本变化见[变更记录](../CHANGELOG.md)。示例使用已发布的 2.0.0 包，不加载仓库根目录的库源码。

| 调用 | 客户端 | 服务端方法 | 消息方向 |
| --- | --- | --- | --- |
| 普通 unary | `client/hello.php` | `SayHello` | 一条请求 → 一条回复 |
| 服务端流 | `client/server-stream.php` | `ServerStreamingEcho` | 一条请求 → 多条回复 |
| 客户端流 | `client/client-stream.php` | `ClientStreamingEcho` | 多条请求 → 一条汇总回复 |
| 双向流 | `client/bidi-stream.php` | `BidiStreamingEcho` | 请求、回复均为流，发送期间即可收到回复 |
| 原生 gRPC 对照 | `client/native.php` | 上面四种调用 | 使用生成的 `GreeterClient` 和 PHP grpc 扩展 |

## 1. 准备依赖

```bash
cd /home/hetao/php-grpc-server-protobuf/samples
composer install --working-dir=libs
```

本次验证使用 PHP 8.4.27。服务端和 Swoole 客户端需要支持 `http2_stream_mode / Swoole\Http\Request::recv()` 的 Swoole 扩展，以及 `libs` 内的 Protobuf 运行库。`native.php` 还需要原生 PHP grpc 扩展。Protobuf C 扩展可选，纯 PHP 运行库也可以运行。

官方包的 Composer 依赖声明要求安装 `ext-swoole` 和 `ext-grpc`；运行服务端和 Swoole 客户端时不调用原生 grpc 扩展，只有 `client/native.php` 使用它。

依赖和加载规则统一声明在 `libs/composer.json`：`require` 固定官方包版本为 `2.0.0`，库类型由该包自己的 Composer 配置加载；示例的 PSR-4 加载服务实现和生成代码，`autoload.files` 加载 `functions.php` 中的公共示例函数。每个入口只加载一次 `libs/vendor/autoload.php`，无需手写 ClassLoader、`spl_autoload_register` 或逐个 require 类文件。

修改加载配置后运行 `composer dump-autoload --working-dir=libs`；使用 `composer dump-autoload --working-dir=libs -o` 可生成优化的类映射。

## 2. 启动服务端

默认监听 `127.0.0.1:50051`，使用明文 HTTP/2（h2c）。新终端中保持服务端前台运行：

```bash
PHP=/data/php8/bin/php
SWOOLE_SO=/home/hetao/swoole-src/modules/swoole.so

"$PHP" -n -d extension="$SWOOLE_SO" server/server.php 127.0.0.1 50051
```

默认是协程式 HTTP Server。也可以选择事件式 Server：

```bash
"$PHP" -n -d extension="$SWOOLE_SO" server/server.php 127.0.0.1 50051 event
```

两种模式择一启动；按 `Ctrl+C` 停止。端口可替换为其他空闲端口，客户端传相同的 host、port。

`server/server.php` 只负责启动和注册 `[GRpcServer::class, 'serve']` 回调。HTTP 方法与媒体类型校验、JSON 请求收集和大小限制、服务调用、流式读写、成功状态和安全错误响应都由 `GRpcServer::serve($request, $response, ...$args)` 处理；额外参数原样传给服务构造方法。

这里用 `-n` 单独加载已修改的扩展，避免加载系统中旧版 Swoole。若当前 PHP 已配置了支持流式请求的新扩展，可以直接使用 `php server/server.php`；仅显示普通 Swoole 版本号不足以判断流式能力，入口会检查 `Request::recv()`。

## 3. 运行客户端

另开终端，仍在 `samples` 目录设置同样的 `PHP`、`SWOOLE_SO`，依次运行：

```bash
PHP=/data/php8/bin/php
SWOOLE_SO=/home/hetao/swoole-src/modules/swoole.so

"$PHP" -n -d extension="$SWOOLE_SO" client/hello.php 127.0.0.1 50051
"$PHP" -n -d extension="$SWOOLE_SO" client/server-stream.php 127.0.0.1 50051
"$PHP" -n -d extension="$SWOOLE_SO" client/client-stream.php 127.0.0.1 50051
"$PHP" -n -d extension="$SWOOLE_SO" client/bidi-stream.php 127.0.0.1 50051
```

普通调用输出：

```text
Hello, Tony!
grpc-status: 0
```

服务端流每隔 100ms 返回一条消息：

```text
server stream #1
server stream #2
server stream #3
grpc-status: 0
```

客户端流先发送三条请求，`closeSend()` 后服务端返回汇总：

```text
send: Alice
send: Bob
send: Carol
Received 3: Alice, Bob, Carol
grpc-status: 0
```

双向流的回复在 `closeSend` 之前出现：

```text
send: Alice
recv: Echo: Alice
send: Bob
recv: Echo: Bob
send: Carol
recv: Echo: Carol
closeSend
grpc-status: 0
```

每个脚本都可以从其他工作目录以绝对路径执行。Swoole 客户端的约定：

- `send()` 发送一条 Protobuf 消息；`recv()` 返回一条消息。
- `closeSend()` 只半关闭请求，之后继续读取回复。
- `recv()` 返回 `null` 表示输入结束且最终 gRPC 状态为 0；错误会抛出异常。
- `finally` 中调用 `close()` 释放连接。
- `GRpcClient` 在协程内创建，默认一次 RPC 共用 5 秒预算。

客户端流服务用 `recv(HelloRequest::class)` 读取到 null 再返回消息；服务端流和双向流用 `yield` 逐条输出。实现见 `app/Test/Helloworld/Greeter.php`。服务端流未设置消息数量时默认返回 3 条，显式数量范围为 1–100，间隔范围为 0–1000ms。

## 4. 原生 PHP gRPC 客户端

服务端保持运行，在已启用原生 grpc 扩展的 PHP 中执行：

```bash
php client/native.php 127.0.0.1 50051
```

此脚本依次演示四种调用，并检查每次最终状态。原生客户端流用 `write()` 发送、`wait()` 半关闭并取汇总；双向流使用 `write() / read() / writesDone() / getStatus()`。最后输出 `all native grpc-status: 0`。

原生客户端可以使用系统 Swoole 旧版或不加载 Swoole，因为这里的 I/O 由原生 grpc 扩展完成。服务端仍须加载支持流式请求的新 Swoole。原生调用选项 `timeout` 的单位是微秒；本例 `5000000` 对应 5 秒。

## 5. 保留的 JSON unary 入口

同一个服务端继续接受 HTTP/1 或 HTTP/2 的 JSON 单次请求：

```bash
curl -H 'Content-Type: application/json' \
  -d '{"name":"Tony"}' \
  http://127.0.0.1:50051/Test.Helloworld.Greeter/SayHello
```

返回 `{"message":"Hello, Tony!"}`。JSON 保留 2MiB 请求上限，适用于 unary；三类流使用标准二进制 gRPC。`User.Info` 目录是历史代码参考，未实现服务接口，不能直接按本手册运行。旧 `run()` 和 FPM 的 `www/index.php` 入口已移除，统一使用 Swoole 的 `serve()` 回调。

## 6. 修改协议并重新生成

协议在 `proto/src/helloworld.proto`，生成代码已保留在 `proto_generated`，可以直接运行。修改协议后执行：

```bash
make proto
```

生成工具统一来自 Docker Hub 镜像 `hetao29/docker-protoc:36.2.1`，包含 protoc 36.2、官方原生 PHP gRPC 插件和 PHP 服务端接口插件 2.0.0，一次命令同时生成消息、客户端及接口。无需安装宿主机 protoc、Go 或使用本目录的旧插件二进制；不手改生成文件。

需要 Docker。默认以 `sudo docker` 调用；已有 Docker 权限时可传 `DOCKER=docker`。首次使用先拉取镜像，也可检查镜像内版本：

```bash
make pull
make versions
make proto
# 不使用 sudo：make proto DOCKER=docker
```

默认固定使用 `36.2.1`，可以通过 `IMAGE` 显式选择其他已验证标签。生成进程使用当前用户的 UID/GID，文件写入 `proto_generated`，不产生 root 所属的新文件；源码目录只读挂载。也可通过 `OUTPUT_DIR` 指定其他输出目录：

```bash
make proto IMAGE=hetao29/docker-protoc:36.2.1
make proto OUTPUT_DIR=/path/to/generated
```

`make` 默认只重新生成 Greeter 协议；也可通过 `PROTO_FILES` 指定其他协议文件。`make server / unary / server-stream / client-stream / bidi-stream / native` 可运行对应入口，支持 `PHP`、`PHP_FLAGS`、`HOST`、`PORT`、`MODE` 变量，例如：

```bash
make server PHP=/data/php8/bin/php \
  PHP_FLAGS='-n -d extension=/home/hetao/swoole-src/modules/swoole.so' MODE=event
```
