Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : TEMPS

**Choix des méthodes à analyser** :

- `getCheapestRoom` 15s.87s
- `getMeta` 4s.18s
- `getReviews` 8s.76s



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 29s.60s

**Temps consommé par `getDB()`** 

- **Avant** 1.52s

- **Après** 194.91ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** 14.67s

- **Après** 11.23s


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** 3.15s  

```sql
SELECT * FROM wp_usermeta
```

- **Après** 171.87ms

```sql
SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = :hotel_id
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 7.91s

```sql
SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 6.22

```sql
SELECT AVG(CAST(wp_postmeta.meta_value AS SIGNED)) AS rating, COUNT(*) AS count
        FROM wp_posts 
        JOIN wp_postmeta ON wp_posts.ID = wp_postmeta.post_id
        WHERE wp_posts.post_author = :hotelId
          AND meta_key = 'rating'
          AND post_type = 'review'
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 15.78s

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 11.40

```sql
SELECT post.ID,
          post.post_title AS title,
          MIN(CAST(PriceData.meta_value AS float)) AS price,
          CAST(SurfaceData.meta_value AS int) AS surface,
          TypeData.meta_value AS types,
          CAST(BedroomsCountData.meta_value AS int) AS bedrooms,
          CAST(BathroomsCountData.meta_value AS int) AS bathrooms,
          CoverImageData.meta_value AS coverImage
        
          FROM tp.wp_posts AS post
        
          INNER JOIN tp.wp_postmeta AS SurfaceData
            ON post.ID = SurfaceData.post_id AND SurfaceData.meta_key = 'surface'
          INNER JOIN tp.wp_postmeta AS PriceData
            ON post.ID = PriceData.post_id AND PriceData.meta_key = 'price'     
          INNER JOIN tp.wp_postmeta AS TypeData
            ON post.ID = TypeData.post_id AND TypeData.meta_key = 'type'
          INNER JOIN tp.wp_postmeta AS BedroomsCountData
            ON post.ID = BedroomsCountData.post_id AND BedroomsCountData.meta_key = 'bedrooms_count'
          INNER JOIN tp.wp_postmeta AS BathroomsCountData
            ON post.ID = BathroomsCountData.post_id AND BathroomsCountData.meta_key = 'bathrooms_count'       
          INNER JOIN tp.wp_postmeta AS CoverImageData
            ON post.ID = CoverImageData.post_id AND CoverImageData.meta_key = 'coverImage'



## Question 5 : Réduction du nombre de requêtes SQL pour `getMetas`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 599       | 599       |
| Temps de `getMetas`          | 188ms     | 186ms     |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 32.21ms   | NOMBRE    |
| Temps de chargement global   | 18.9s     | TEMPS     |

**Requête SQL**

```SQL
-- GIGA REQUÊTE
-- INDENTATION PROPRE ET COMMENTAIRES SERONT APPRÉCIÉS MERCI !
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`

**Requête SQL d'ajout des indexes** 

```sql
-- REQ SQL CREATION INDEXES
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | TEMPS       | TEMPS        |
| `OneRequestService`            | TEMPS       | TEMPS        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `rooms` (1 200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `reviews` (19 700 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
