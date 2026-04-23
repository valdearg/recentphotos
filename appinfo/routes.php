<?php
return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'images#index', 'url' => '/api/images', 'verb' => 'GET'],
		['name' => 'images#view', 'url' => '/view', 'verb' => 'GET'],
		['name' => 'settings#getPersonal', 'url' => '/api/settings/personal', 'verb' => 'GET'],
		['name' => 'settings#savePersonal', 'url' => '/api/settings/personal', 'verb' => 'POST'],
		['name' => 'settings#getAdmin', 'url' => '/api/settings/admin', 'verb' => 'GET'],
		['name' => 'settings#saveAdmin', 'url' => '/api/settings/admin', 'verb' => 'POST'],
		['name' => 'index#getStatus', 'url' => '/api/index/status', 'verb' => 'GET'],
		['name' => 'index#rebuild', 'url' => '/api/index/rebuild', 'verb' => 'POST'],
	],
];
