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
| Nombre d'appels de `getDB()` | 599       | ..        |
| Temps de chargement global   | 18.9s     | 2.02s     |

**Requête SQL**

```SQL
$query = "SELECT
		user.ID AS id,
	display_name AS name,
	latData.meta_value AS lat,
	lngData.meta_value AS lng,
	mailData.meta_value AS mail,
	phoneData.meta_value AS phone,
	addr1Data.meta_value AS address_1,
	addr2Data.meta_value AS address_2,
	cityData.meta_value AS address_city,
	zipData.meta_value AS address_zip,
	countryData.meta_value AS address_country,
	imageData.meta_value AS cover_image,
	$selectDistanceKM
	
	review.count AS review_count,
	review.rating AS review_rating,
	
	post.ID AS cheapestRoomId,
	post.price AS price,
	post.surface AS surface,
	post.bedRoomsCount AS bed_rooms_count,
	post.bathRoomsCount AS bath_rooms_count,
	post.roomType AS type
	
FROM
	wp_users AS USER
	
	-- geo coords
	INNER JOIN tp.wp_usermeta AS latData ON latData.user_id = user.ID
		AND latData.meta_key = 'geo_lat'
	INNER JOIN tp.wp_usermeta AS lngData ON lngData.user_id = user.ID
		AND lngData.meta_key = 'geo_lng'
	-- email
	INNER JOIN tp.wp_usermeta AS mailData ON mailData.user_id = user.ID
		AND mailData.meta_key = 'email'
	-- phone
	INNER JOIN tp.wp_usermeta AS phoneData ON phoneData.user_id = user.ID
		AND phoneData.meta_key = 'phone'
	-- address_1
	INNER JOIN tp.wp_usermeta AS addr1Data ON addr1Data.user_id = user.ID
		AND addr1Data.meta_key = 'address_1'
	-- address_2
	INNER JOIN tp.wp_usermeta AS addr2Data ON addr2Data.user_id = user.ID
		AND addr2Data.meta_key = 'address_2'
	-- address_city
	INNER JOIN tp.wp_usermeta AS cityData ON cityData.user_id = user.ID
		AND cityData.meta_key = 'address_city'
	-- address_zip
	INNER JOIN tp.wp_usermeta AS zipData ON zipData.user_id = user.ID
		AND zipData.meta_key = 'address_zip'
	-- address_country
	INNER JOIN tp.wp_usermeta AS countryData ON countryData.user_id = user.ID
		AND countryData.meta_key = 'address_country'
	-- image
	INNER JOIN tp.wp_usermeta AS imageData ON imageData.user_id = user.ID
		AND imageData.meta_key = 'coverImage'
	
	-- rating
	INNER JOIN (
	SELECT
		post.post_author,
		COUNT( CAST(meta.meta_value AS UNSIGNED) ) as count,
		AVG( CAST(meta.meta_value AS UNSIGNED) ) as rating
	FROM
		tp.wp_posts as post
		INNER JOIN tp.wp_postmeta as meta ON post.ID = meta.post_id
	WHERE
		post_type = 'review'
	GROUP BY
		post_author
	) AS review ON user.ID = post_author
	
	-- room
  INNER JOIN (
		SELECT
			post.ID,
			post.post_title,
			post.post_author,
			MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
			CAST(surfaceData.meta_value AS UNSIGNED) as surface,
			CAST(bedData.meta_value AS UNSIGNED) as bedRoomsCount,
			CAST(bathData.meta_value AS UNSIGNED) as bathRoomsCount,
			typeData.meta_value as roomType
		FROM
			tp.wp_posts AS post
			-- price
			INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id
				AND priceData.meta_key = 'price'
			-- surface
			INNER JOIN tp.wp_postmeta AS surfaceData ON surfaceData.post_id = post.ID
				AND surfaceData.meta_key = 'surface'
			-- bedrooms count
			INNER JOIN tp.wp_postmeta AS bedData ON bedData.post_id = post.ID
				AND bedData.meta_key = 'bedrooms_count'
			-- bathrooms count
			INNER JOIN tp.wp_postmeta AS bathData ON bathData.post_id = post.ID
				AND bathData.meta_key = 'bathrooms_count'
			-- room type
			INNER JOIN tp.wp_postmeta AS typeData ON typeData.post_id = post.ID
				AND typeData.meta_key = 'type'
		WHERE
			post_type = 'room'
		GROUP BY
			post.ID) AS post ON user.ID = post.post_author
";
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
