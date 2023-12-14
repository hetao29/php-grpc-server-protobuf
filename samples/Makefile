ROOT_DIR:=$(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))
all:
	#其它语言的生成，请参考, https://github.com/hetao29/docker-protoc
	#生成client
	find proto/src/ -name "*.proto" | xargs -I {} sudo docker run --rm -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR} hetao29/docker-protoc:latest protoc \
		--plugin=protoc-gen-grpc=/usr/bin/grpc_php_plugin --grpc_out=proto_generated --php_out=proto_generated -I proto/ -I . \
		"{}"
	#生成服务端interface
	find proto/src/ -name "*.proto" | xargs -I {} sudo docker run --rm -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR} hetao29/docker-protoc:latest protoc \
		--plugin=protoc-gen-grpc=/usr/bin/protoc-gen-php-grpc --grpc_out=proto_generated --php_out=proto_generated -I proto/ -I . \
		"{}"
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
