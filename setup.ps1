
docker-compose up --build -d

Start-Sleep -Seconds 60

docker-compose exec app composer install
