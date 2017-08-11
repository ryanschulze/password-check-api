<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$app = new \Slim\App(["settings" => $config]);

function is_sha1($str) {
    return (bool) preg_match('/^[0-9a-f]{40}$/i', $str);
}

// API Paths

$app->get('/sha1/{sha1sum}', function (Request $request, Response $response) {
	require __DIR__ . '/../db_config.php';

	$sha1sum = $request->getAttribute('sha1sum');
	if ( is_sha1($sha1sum) ) {
		$sha1sum = strtoupper($sha1sum);

		$pdo = new \Slim\PDO\Database(
			'mysql:host='.$config['db']['host'].';dbname='.$config['db']['dbname'].';charset=utf8',
			$config['db']['user'], $config['db']['pass']);

		$selectStatement = $pdo->select()
			->from('pwdlist')
			->where('pwd', '=', $sha1sum);

		$stmt = $selectStatement->execute();
		if ( $stmt->fetch() ) {
			// Password was found in the database
			$response = $response->withStatus(406);
		} else {
			// Password was not found in the database
			$response = $response->withStatus(204);
		}
	} else {
		// Input not a valid SHA1 hash
		$response = $response->withStatus(400);
	}
	return $response;
});

$app->get('/cleartext/{password}', function (Request $request, Response $response) {
	require __DIR__ . '/../db_config.php';

	$password = $request->getAttribute('password');
	$password = strtoupper(sha1($password));

	$pdo = new \Slim\PDO\Database(
		'mysql:host='.$config['db']['host'].';dbname='.$config['db']['dbname'].';charset=utf8',
		$config['db']['user'], $config['db']['pass']);

	$selectStatement = $pdo->select()
		->from('pwdlist')
		->where('pwd', '=', $password);

	$stmt = $selectStatement->execute();
	if ( $stmt->fetch() ) {
		// Password was found in the database
		$response = $response->withStatus(406);
	} else {
		// Password was not found in the database
		$response = $response->withStatus(204);
	}
	return $response;
});

$app->run();
