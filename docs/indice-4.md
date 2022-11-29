### ℹ️ Indice n°4 : Comment gérer l'écriture des `WHERE` en fonction des conditions de `$args` ?

Vous pouvez écrire votre requête SQL dans une variable `$query` jusqu'aux instructions `WHERE`. Créez ensuite un tableau `$whereClauses = []`, qui contiendra vos conditions. Ensuite, dans vos blocs `if`, si une entrée de `$arg` vous indique qu'il faut ajouter une condition, ajoutez votre instruction SQL dans votre tableau. Ensuite, si votre tableau a au moins une entrée, faites `query .= implode(' AND ', $whereClauses );`.

```php
// Requête de base jusqu'aux WHERE
$query = "SELECT * FROM tp.wp_posts AS post";

$whereClauses = [];

// Si l'utilisateur filtre sur cette donnée, alors on ajoute une condition SQL
if ( isset( $args['myFilter'] ) )
  $whereClauses[] = 'myFilter >= :myFilter';

// Si on a des clauses WHERE, alors on les ajoute à la requête
if ( count($whereClauses > 0) )
  $query .= " WHERE " . implode( ' AND ', $whereClauses );

// On récupère le PDOStatement
$stmt = $pdo->prepare( $query );

// On associe les placeholder aux valeurs de $args,
// on doit le faire ici, car nous n'avions pas accès au $stmt avant
if ( isset( $args['myFilter'] ) )
  $stmt->bindParam('myFilter', $args['myFilter'], PDO::PARAM_INT);

$stmt->execute();
```
