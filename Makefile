.PHONY: up install seed test inspector watch down

up:
	docker compose up -d --build

install:
	docker compose exec app composer install

seed:
	docker compose exec -T app php bin/seed

test:
	docker compose exec -T app vendor/bin/phpunit

inspector:
	@echo "Transport: Streamable HTTP · URL: http://localhost:8080/mcp · Authentication: Bearer dev-secret"
	npx -y @modelcontextprotocol/inspector

watch:
	docker compose exec app php bin/mcp-watch $(ARGS)

down:
	docker compose down
