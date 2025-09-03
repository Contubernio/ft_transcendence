IMAGE_NAME=transcendence-app
CONTAINER_NAME=transcendence

build:
	docker build -t $(IMAGE_NAME) .

up: build
	docker run -d \
		--name $(CONTAINER_NAME) \
		-p 8080:80 \
		-v $(PWD)/data:/var/www/html/data \
		$(IMAGE_NAME)

down:
	docker stop $(CONTAINER_NAME) || true
	docker rm $(CONTAINER_NAME) || true

logs:
	docker logs -f $(CONTAINER_NAME)

shell:
	docker exec -it $(CONTAINER_NAME) /bin/bash
