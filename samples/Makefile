all:
	#https://grpc.io/docs/languages/php/quickstart/
	#生成interface
	protoc --plugin=protoc-gen-php-grpc=proto/protoc-gen-php-grpc --php-grpc_out=proto_generated --php_out=proto_generated -I proto/ proto/src/*
	#生成client
	protoc --plugin=protoc-gen-grpc=proto/grpc_php_plugin --grpc_out=proto_generated --php_out=proto_generated -I proto/ proto/src/*
install:
	sudo apt install -y protobuf-compiler
	sudo pecl install grpc
	sudo pecl install swoole
link:
	sudo ln -s /data/php-grpc-server-protobuf/samples/conf/supervisor.conf /etc/supervisor/conf.d/grpc.samples.conf
	sudo supervisorctl reread
	sudo supervisorctl update
	sudo supervisorctl restart grpc:samples
restart:
	sudo supervisorctl restart grpc:samples
