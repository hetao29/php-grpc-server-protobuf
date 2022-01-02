all:
	#https://grpc.io/docs/languages/php/quickstart/
	protoc --plugin=protoc-gen-grpc=grpc_php_plugin --grpc_out=../proto_generated --php_out=../proto_generated src/*
install:
	sudo apt install -y protobuf-compiler
