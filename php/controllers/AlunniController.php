<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AlunniController
{
  public function index(Request $request, Response $response, $args){
    $db = DB::getInstance();
    $alunni = $db->select("alunni");

    $response->getBody()->write(json_encode($alunni));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function show(Request $request, Response $response, $args){
    // $queryParams = $request->getQueryParams();
    // var_dump($queryParams);
    // exit;
    // curl http://localhost:8080/alunni/1
    $db = DB::getInstance();
    $alunno = $db->selectOne("alunni", ["id" => $args["id"]]);

    $response->getBody()->write(json_encode($alunno, true));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function search(Request $request, Response $response, $args){
    // $queryParams = $request->getQueryParams();
    // var_dump($queryParams);
    // exit;
    // curl http://localhost:8080/alunni/search?column=nome&sort=a&query=a
    $queryParams = $request->getQueryParams();
    $column = $queryParams['column'] ?? 'nome';
    $query = $queryParams['query'] ?? '';
    $sort = $queryParams['sort'] ?? 'a';

    switch ($sort) {
      case 'a':
        $sort = 'ASC';
        break;
      case 'd':
        $sort = 'DESC';
        break;
      default:
        $sort = 'ASC';
    }

    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $stmt = $mysqli_connection->prepare("DESCRIBE alunni");
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = $result->fetch_all(MYSQLI_ASSOC);

    $found = false;
    foreach ($columns as $col) {
      if ($col['Field'] == $column) {
        $found = true;
        break;
      }
    }

    if (!$found) {
      $response->getBody()->write(json_encode(["msg" => "Column not found"]));
      return $response->withHeader("Content-type", "application/json")->withStatus(404);
    }

    $stmt = $mysqli_connection->prepare("SELECT * FROM alunni WHERE nome LIKE CONCAT('%', ?, '%') OR cognome LIKE CONCAT('%', ?, '%') ORDER BY $column $sort");
    $stmt->bind_param("ss", $query, $query);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    //                          Serializzazione in json 
    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function create(Request $request, Response $response) {
    // curl -X POST http://localhost:8080/alunni -H "Content-Type: application/json" -d '{"nome": "Gioele","cognome": "Spata"}'

    $db = DB::getInstance();

    // Recupera i dati dal body della richiesta (JSON)
    $data = json_decode($request->getBody()->getContents(), true);
    
    // Prepara la query SQL (usa backtick per i nomi di tabelle/colonne)
    $newId = $db->insert("alunni", [
      "nome" => $data['nome'],
      "cognome" => $data['cognome'],
    ]);
    
    //Risposta di successo
    $response->getBody()->write(json_encode([
      'status' => 'success',
      'message' => 'Alunno creato con successo',
      'id' => $newId
    ]));
    
    return $response->withHeader("Content-type", "application/json")->withStatus(201); 
  }

  public function update(Request $request, Response $response, $args) {
    //curl -X PUT http://localhost:8080/alunni/3 -H "Content-Type: application/json" -d '{"nome": "Ruji"}'
    $db = DB::getInstance();
    // Recupera i dati dal body della richiesta (JSON)
    $data = json_decode($request->getBody()->getContents(), true);
    
    // Prepara la query SQL
    $db->update("alunni", ["nome", "id"], ["nome" => $data["nome"], "id" => $args["id"]]);
    
    //Risposta di successo
    $response->getBody()->write(json_encode([
      'status' => 'success',
      'message' => 'Alunno Aggiornato con successo',
    ]));
    
    return $response->withHeader("Content-type", "application/json")->withStatus(201); 
  }

  public function destroy(Request $request, Response $response, $args) {
    //curl -X DELETE http://localhost:8080/alunni/2
    $db = DB::getInstance();
    
    // Prepara la query SQL
    $db->delete("alunni", ["id" => $args["id"]]);
    
    //Risposta di successo
    $response->getBody()->write(json_encode([
      'status' => 'success',
      'message' => 'Alunno Eliminato con successo',
      'id' => $args['id'],  
    ]));
    
    return $response->withHeader("Content-type", "application/json")->withStatus(201); 
  }
}