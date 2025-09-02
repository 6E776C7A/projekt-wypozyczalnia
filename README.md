# projekt-wypozyczalnia
Strona internetowa wypożyczalni samochodowej.

# Wymagania:

Posiadanie zainstalowanego DockerDesktop.

# Instrukcja do obsługi projektu:

  1. Po sklonowaniu repozytorium należy wykonać komende:

    # docker-compose up -d --build (inicjalizacja kontenera na Docker)

  2. Następnie komenda:

    # docker-compose exec app composer install (tworzy ona composer w kontenerze, wykonujemy tylko raz)

  3. Zmiany zarówno w VSC i w kontenerze zmiany się zapisują się lokalnie i w kontenerze.

  4. Aby połączyć się z kontenerem należy wykonać komendę:

    # docker-compose exec app bash

    # exit (wyjscie z terminala kontenera)

  7. Można bazę podglądać też w VSC za pomocą rozszerzenia SQLite Viewer

  8. Wpisanie komendy:

    # docker-compose down (wyłączenie kontenera oraz go wyczyszczenie, należy następnym razem wykonać pkt nr.1)

  ## 9. !!! Baza danych nie jest wysyłana na gita tylko jest LOKALNIE !!!

  10. Po dodaniu dodatkowych plików w VSC gdy kontener jest włączony należy wykonać:

    # docker-compose up -d (Aktualizacja plików kontenera)

  11. Przydatne komendy Docker:

    # docker -ps (Lista działających kontenerów)

    # docker-compose stop (zatrzymuje kontener nie czyszcząc go)

    # docker-compose restart (restartuje kontener)

    # docekr-compose up -d (startuje kontener)
  12. Strona dostępna jest pod adresem:
  
    # localhost:8080