# กะเพราหมูกรอบ

### Start Project (First time only)

``` shell
cd <root>
docker-compose up composer
```

### Run Project

``` shell
cd <root>
docker-compose up
```

Then, you can access the site in http://localhost:8000/

### Generate Database

``` shell
cd <root>
docker exec sec2_krapaomookrob_laravel_1 php artisan migrate:refresh --seed
```

The database can be managed by phpmyadmin on http://localhost:8081/
- username: `dbuser`
- password: `p455w0rd`

## Members

* Niti Assavaplakorn
* Peeranuth Kehasukcharoen
* Pollawat Hongwimol
* Thanawat Jierawatanakanok
* Nithipud Tunticharoenviwat
* Krit Kruaykitanon
* Nuttanai Kijviwattanakarn
* Wiwanna Phaithoonmongkon