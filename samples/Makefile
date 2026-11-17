ROOT_DIR := $(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))
PHP ?= php
PHP_FLAGS ?=
DOCKER ?= sudo docker
IMAGE ?= hetao29/docker-protoc:36.2.1
OUTPUT_DIR ?= $(ROOT_DIR)/proto_generated
PROTO_FILES ?= src/helloworld.proto
HOST ?= 127.0.0.1
PORT ?= 50051
MODE ?= coroutine

.PHONY: all proto pull versions server unary server-stream client-stream bidi-stream native
all: proto

proto:
	mkdir -p "$(OUTPUT_DIR)"
	$(DOCKER) run --rm --user "$(shell id -u):$(shell id -g)" \
		-v "$(ROOT_DIR):/workspace:ro" -v "$(abspath $(OUTPUT_DIR)):/generated" \
		-w /workspace/proto $(IMAGE) protoc -I . -I /usr/local/include \
		--plugin=protoc-gen-grpc=/usr/bin/grpc_php_plugin \
		--plugin=protoc-gen-php-grpc=/usr/bin/protoc-gen-php-grpc \
		--php_out=/generated --grpc_out=/generated --php-grpc_out=/generated $(PROTO_FILES)

pull:
	$(DOCKER) pull $(IMAGE)

versions:
	$(DOCKER) run --rm $(IMAGE) protoc --version
	$(DOCKER) run --rm $(IMAGE) protoc-gen-php-grpc --version

server:
	$(PHP) $(PHP_FLAGS) "$(ROOT_DIR)/server/server.php" "$(HOST)" "$(PORT)" "$(MODE)"

unary:
	$(PHP) $(PHP_FLAGS) "$(ROOT_DIR)/client/hello.php" "$(HOST)" "$(PORT)"

server-stream client-stream bidi-stream native:
	$(PHP) $(PHP_FLAGS) "$(ROOT_DIR)/client/$@.php" "$(HOST)" "$(PORT)"
