<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AlunniController.php';
require __DIR__ . '/controllers/CertificazioniController.php';
require_once __DIR__ .  "/includes/DB.php";

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// alunni
$app->get('/alunni', "AlunniController:index");
$app->get('/alunni/search', "AlunniController:search");
$app->get('/alunni/{id}', "AlunniController:show");
$app->post('/alunni', "AlunniController:create");
$app->put('/alunni/{id}', "AlunniController:update");
$app->delete('/alunni/{id}', "AlunniController:destroy");

// certificazioni
$app->get("/alunni/{id}/certificazioni", "CertificazioniController:index");
$app->post("/alunni/{id: /d+}/certificazioni", "CertificazioniController:create");
$app->put("/alunni/{id_alunni}/certificazioni/{id_certificazioni}", "CertificazioniController:update");
$app->delete("/alunni/{id_alunni}/certificazioni[/{id_certificazioni}]", "CertificazioniController:destroy");

$app->run();
