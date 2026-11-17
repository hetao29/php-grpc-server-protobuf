# 变更记录

## 2.0.1（待发布）

当前源码版本为 2.0.1；版本声明和本节说明不代表已发布 Composer 包或创建 Git 标签。此补丁不改变协议和服务接口，继续使用接口生成器 2.0.0。

- 二进制异常响应同时保留普通 `grpc-status/grpc-message` 头与最终 trailers，避免空 body 经 Nginx grpc_pass 后丢失状态；已发送消息的错误仍以最终 trailers 为准。
- 修复 unary 响应超过 16 MiB 被错误拒绝的问题；仅跳过单请求、单响应的出站检查，入站线上/解压限额和流式默认限额保持不变。
- `GRpcStream::send` 新增可选 `enforceSizeLimit` 参数，默认 true；false 仅影响当前发送。

## 2.0.0

2.0.0 引入以下服务端、客户端及加载方式调整，配套接口生成器版本为 2.0.0。

### 新增

- `GRpcClient` 协程客户端，统一支持 unary、服务端流、客户端流与双向流，提供半关闭、最终状态校验、整次调用期限和独立连接生命周期。
- `GRpcStream` 增量消息读写，处理跨 DATA 帧消息、每消息 gzip 及大小限制。
- 两种 Swoole Server 模式下的四类 RPC 示例，以及官方原生 grpc 客户端对照示例。

### 调整

- `GRpcServer::serve(Request, Response, ...构造参数)` 统一承接二进制与 JSON unary、HTTP 校验、服务调度、响应和错误处理。
- Composer `autoload.classmap` 按需加载库类型；示例通过 PSR-4 和 `autoload.files` 加载业务类与公共函数。
- 示例 Makefile 统一使用 Docker Hub 镜像 `hetao29/docker-protoc:36.2.1` 生成消息、原生客户端和服务端接口。
- 示例通过 Composer 固定引用官方 `hetao29/php-grpc-server-protobuf:2.0.0`，移除指向根目录库源码的本地 classmap。
- JSON 请求体统一限制为 2 MiB；二进制每条消息的线上与解压 payload 保持 16 MiB 上限。
- 失败统一输出安全状态 13，服务方法限定为具有具体请求类参数的公共实例方法。

### 不兼容变化

- 移除字符串参数形式的 `run()`、全局输入读取方法 `getRawData()` 及旧 FPM 示例入口。
- 新的流式接口须使用配套 2.0.0 生成器重新生成，并同步修改实现。
- 流式功能须同时使用匹配的 Swoole 传输补丁、PHP 库和生成接口；仅更新 Composer 包不能替代扩展升级。

迁移步骤见 [UPGRADE.md](UPGRADE.md)，API 与限制见 [MANUAL.md](MANUAL.md)。
