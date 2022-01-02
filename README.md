# php-protobuf-server
The php protobuf server framework

# Architecture

gRPC Client  => nginx [gRPC Gateway](https://www.nginx.com/blog/deploying-nginx-plus-as-an-api-gateway-part-3-publishing-grpc-services/) => php-fpm => this framework

# Process

1. proto and genproto to php files
2. write gRPC Server 
3. run
