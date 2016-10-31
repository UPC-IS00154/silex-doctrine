<?php

require_once __DIR__ . "/vendor/autoload.php";

// uso de las clases Request y Response de Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// uso de las clases para crear un esquema en Doctrine
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;

// Crea la Aplicación 
// ==================

$app = new Silex\Application();

// configurar el generador de URLs
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// configura el manejo de sesiones
$app->register(new Silex\Provider\SessionServiceProvider());

// configurar Twig en la Aplicación 
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
));

// configura la conexión a la base de datos
$app->register(
  new Silex\Provider\DoctrineServiceProvider(), 
  array(
    'db.options' => array(
      'dbname' => 'ejemplo',
  	  'host' => 'localhost',
  	  'user' => 'root',
  	  'password' => 'root',
  	  'driver' => 'pdo_mysql',
      'charset'       => 'utf8',
      'driverOptions' => array(1002 => 'SET NAMES utf8',),
    ),
  )
);

// Crea las tablas si no existen
$schema = $app['db']->getSchemaManager();
if (!$schema->tablesExist('users')) {
    $users = new Table('users');
    $users->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
    $users->setPrimaryKey(array('id'));
    $users->addColumn('username', 'string', array('length' => 32));
    $users->addUniqueIndex(array('username'));
    $users->addColumn('password', 'string', array('length' => 255));
    $users->addColumn('roles', 'string', array('length' => 255));

    $schema->createTable($users);

    $app['db']->insert('users', array(
      'username' => 'fabien',
      'password' => '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==',
      'roles' => 'ROLE_USER'
    ));

    $app['db']->insert('users', array(
      'username' => 'admin',
      'password' => '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==',
      'roles' => 'ROLE_ADMIN'
    ));
}

// == Redirige el navegador en Koding

// el URL inicia con "//"
if ( substr($_SERVER['REQUEST_URI'], 0, 2) == '//') {
  // redirige el navegador para que el URL no tenga "//"
  header('Location: '.substr($_SERVER['REQUEST_URI'],1));
  die;
}

// == ejecuta pruebas sobre la base de datos

// http://doctrine-orm.readthedocs.io/projects/doctrine-dbal/en/latest/reference/data-retrieval-and-manipulation.html
// http://doctrine-orm.readthedocs.io/projects/doctrine-dbal/en/latest/reference/query-builder.html
// http://doctrine-orm.readthedocs.io/projects/doctrine-dbal/en/latest/reference/transactions.html
// http://doctrine-orm.readthedocs.io/projects/doctrine-dbal/en/latest/reference/schema-manager.html

// muestra los mensajes de error
$app['debug'] = true;

/*
echo "<pre>";

echo "== select (todos)<br/>";
$users = $app['db']->fetchAll("SELECT * FROM users");
print_r($users);

echo "== select (admin)<br/>";
$users = $app['db']->fetchAssoc(
  'SELECT * FROM users WHERE username = ?',
  array('admin'));
print_r($users);

echo "== query builder<br/>";
$queryBuilder = $app['db']->createQueryBuilder();
echo $queryBuilder
    ->select('id', 'username')
    ->from('users')
    ->where('username = ?')
    ->setParameter(0, 'admin');

echo "<br/>";
echo "== insert<br/>";
$app['db']->insert('users', array(
  'username' => 'otto',
  'password' => '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==',
  'roles' => 'ROLE_ADMIN'
));

echo "== update<br/>";
$app['db']->update('users', 
  array('roles' => 'ROLE_USER'),
  array('username' => 'otto')
);

echo "== delete<br/>";
$app['db']->delete('users', array(
  'username' => 'otto'
));

echo "<pre>";
*/

// == Rutas

$app->get( "/", function() use ($app){
  
  // traer los datos de los usuarios
  $datos = $app['db']->fetchAll("select * from users");
  
  // mostrar en pantall
  return $app['twig']->render( 
    'lista-usuarios.twig.html', 
    array(
      'users' => $datos 
    ));
  
})->bind('user-list');

// /edit muestra el formulario
$app->get("/edit", function () use ($app) {
  
  // muestra el formulario de usuario
  // no manda ningún dato a la plantilla 
  return $app['twig']->render(
    'forma-usuarios.twig.html',
    array(
        "user" => array (
            'id' => '',
            'username' => '',
            'roles' => ''
          )
      )
    );
  
})->bind('new-user');


$app->get("/edit/{id}", function ($id) use ($app) {
  
  $datos = $app['db']->fetchAssoc(
    "select * from users where id = ?", 
    array($id)
  );
  
  // muestra el formulario de usuario
  // no manda ningún dato a la plantilla 
  return $app['twig']->render(
    'forma-usuarios.twig.html',
    array(
        "user" => $datos
      )
    );
  
})->bind('edit-user');

$app->post("/save", function(Request $request) use ($app) {
  
  $id = $request->get("id");
  
  if ( $id == '') {
    $app['db']->insert('users',
      array(
          'username' => $request->get("username"),
          'roles' => $request->get("roles")
        )
    );
  } else {
    $app['db']->update('users',
      array(
          'username' => $request->get("username"),
          'roles' => $request->get("roles")
        ),
      array(
          'id' => $id
        )
    );
  }
  
  // redirige el navegador a la lista
  return $app->redirect( $app['url_generator']->generate('user-list') );
  
})->bind('save-user');


$app['debug'] = true;
$app->run();