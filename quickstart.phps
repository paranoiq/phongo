<?php

// následuje mystery code (ne vše je aktuálně implementováno)

// snažím přiblížit syntaxi javascriptové konzoly MongoDB, případně ji ještě zjednodušit

// statické rozhraní ála dibi:: není zahrnuto. není to good practise

// veškeré metody, které nevrací výsledek a má to u nich smysl vrací $this (fluent interface)


$options = array(
    'servers' => array('127.0.0.1'),
    'username' => $password,
    'password' => $password);

// objekt spojení
$conn = new Phongo\Connection($options);


// DATABÁZE A PŘÍKAZY --------------------------------------------------------------------------------------------------

// výběr databáze - vrátí objekt Phongo\Database
$db = $conn->database('myDatabase');
// nebo jednodušeji
$db = $conn->myDatabase;

// příkaz v PHP syntaxi
$db->runCommand(array('dbStats' => 1));
// totéž v JSON syntaxi
$db->runCommand('{"dbStats": 1}');
// zkratka pro příkaz bez parametrů (1 je defaultní parametr)
$db->runCommand('dbStats');

// přímo na objektu spojení
$conn->myDatabase->runCommand('dbStats');
// ... dynamické jméno databáze
$conn->database($database)->runCommad('dbStats');


// KOLEKCE -------------------------------------------------------------------------------------------------------------

// __get() a useCollection() na databázi opět vrací objekt databáze!
// kolekce není samostatný objekt, narozdíl od implementace v rozšíření PHP/Mongo
//
// useCollection() kolekci vybere pro další práci s ní. není již dále nutné uvádět jméno kolekce.
// __get() kolekci nevybírá natrvalo, ale pouze do prvního dotazu na ní. dotaz je možné zřetězit 
// a přistupovat tak ke jmenným prostorům kolekcí

// query
$db->myCollection->find($conditions, $fields);
// nebo
$db->collection($collection)->find($conditions, $fields);
// nebo
$db->find($conditions, $fields, $collection);

// pro víc dotazů na stejné kolekci
// zůstává v platnosti dokud není změněn namespace (i když je mezitím přistupováno k jiným kolekcím)
$db->useCollection($collection/*[, $namespace]*/);
$db->find($conditions);
$db->find($otherConditions);
//...

// kolekce s namespace: myBlog.posts atp.
$db->useNamespace('myBlog');
$db->posts->find(); // dotaz: db.myBlog.post.find()
$db->users->find();
//...

// namespace jako tečková notace v JS konzoli
$db->ns1->ns2->ns3->posts->find(); // dotaz: db.ns1.ns2.ns3.posts.find()


// DOTAZY --------------------------------------------------------------------------------------------------------------

$db->collection('myCollection');

// PHP syntax podmínek
$db->find(array('sloupec' => 123, 'jinýSloupec' => 456));
// JSON syntax
$db->find('{"sloupec": 123, "jinýSloupec": 456}');

// vybrat jen některá pole výsledku - druhý argument
$db->find($conditions, $fields);

// vybrat jen první nalezený výsledek
$db->findOne($conditions);

// zkrácená syntaxe pro hlavní klíč - vrátí jeden výsledek (pokud je nalezen)
$key = 'af45600e8b684a8600000942'; // ID objektu
$db->find($key);
// lépe
$db->findOne($key);
// nejlépe
$db->get($key);
// upe nejlépe! (reference je odkaz na objekt v databázi)
$db->get(new Phongo\Reference($key, 'myCollection'/*[, $database]*/));


// ostatní CRUD operace

// nová položka - objekt nesmí mít ID, pokud je generováno automaticky
$db->insert($objekt);
// uložení existující položky (obdoba SQL "REPLACE ..."). objekt musí mít ID
$db->save($objekt);
// odstranění objektů
$db->delete($conditions);
// hromadný update
$db->update($conditions, $changes);
// update/insert - pokud není nalezen žádný vyhovující záznam, sloučí se podmínky a změny a výsledný objekt se založí
$db->update($conditions, $changes, TRUE);

// všechny přijímají jak PHP, tak JSON syntax
// update() a delete() opět přijímají místo podmínek i ID nebo referenci


// VÝSLEDEK ------------------------------------------------------------------------------------------------------------

// find() vrací výsledek - Phongo\Cursor
// vrácený kursor není zatím proveden. dotaz je vykonán až v okamžiku kdy chcete po kursoru data
$cur = $db->myCollection->find($conditions);

// výběr vrácených polí výsledku
$cur->setFields(array('jnéno', 'příjmení'));

// limit a offset
$cur->setLimit($limit/*[, $offset]*/);
$cur->setOffset($offset);

// řazení
$cur->orderBy($keys);
// 1 vzestupně, -1 sestupně
$cur->orderBy(array('aaa' => 1, 'bbb' => -1));

// načítání položek výsledku
while ($item = $cur->fetch()) {
    ///
}
// nebo iterace přes výsledek
foreach ($cur as $item) {
    ///
}

// načtení všech položek
$all = $curr->fetchAll();

// počet nalezených položek
$cur->count();
// počet vrácených položek (bere v úvahu limit a offset)
$cur->count(TRUE);
// nebo alternativně - vrátí místo kursoru rovnou počet
$db->myCollection->find($conditions)->count();
// nebo rovnou zkráceně
$db->myCollection->count($conditions);

// velikost výsledku (na velkých kolekcích pomalé)
$cur->size();
// jako výše fungují i varianty
$db->myCollection->find($conditions)->size();
$db->myCollection->size($conditions);

