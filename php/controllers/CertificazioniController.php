<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CertificazioniController
{
    public function index(Request $request, Response $response, $args)
    {
        $db = DB::getInstance();
        $certificazioni = $db->select("certificazioni", ["alunno_id" => $args["id"]]);

        $response->getBody()->write(json_encode($certificazioni, true));
        return $response->withHeader("Content-type", "application/json")->withStatus(200);
    }

    public function create(Request $request, Response $response, $args) 
    {
        // curl -X 'POST' -d '{"alunno_id": 3, "titolo": "CREATA ORA", "votazione": 54, "ente": "CREATA ORA"}' http://localhost:8080/alunni/3/certificazioni

        $db = DB::getInstance();
        $data = json_decode($request->getBody()->getContents(), true);

        $newId = $db->insert("certificazioni", $data);

        $response->getBody()->write(json_encode($newId, true));
        return $response->withHeader("Content-type","application/json")->withStatus(200);
    }

    public function update(Request $request, Response $response, $args) 
    {
        // curl -X 'PUT' -d '{"alunno_id": 3, "titolo": "MODIFICATA ORA", "votazione": 100, "ente": "MODIFICATA ORA"}' http://localhost:8080/alunni/3/certificazioni/4

        $db = DB::getInstance();
        $data = json_decode($request->getBody()->getContents(), true);

        $affectedRows = $db->update("certificazioni", $data, [
            "id"=> $args["id_certificazioni"], 
            "alunno_id" => $args["id_alunni"]
        ]);

        $response->getBody()->write(json_encode($affectedRows, true));
        return $response->withHeader("Content-type","application/json")->withStatus(200);
    }

    public function destroy(Request $request, Response $response, $args) 
    {
        // curl -X 'DELETE' http://localhost:8080/alunni/3/certificazioni/4

        $db = DB::getInstance();

        $affectedRows = 0;
        if (!isset($args["id_certificazioni"])) {
            $affectedRows = $db->delete("certificazioni", ["alunno_id" => $args["id_alunni"]]);
        } else {
            $affectedRows = $db->delete("certificazioni", [
                "id" => $args["id_certificazioni"], 
                "alunno_id" => $args["id_alunni"]
            ]);
        }
        $result =[
            "result"=> $affectedRows>0,
            "message"=>$affectedRows>0?"Cancellazione avvenuta con successo":"Errore cancellazione"
        ];
        $response->getBody()->write(json_encode($result));
        return $response->withHeader("Content-type","application/json")->withStatus(200);
    }
}
