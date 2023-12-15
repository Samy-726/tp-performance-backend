<?php
 
namespace App\Common;
 
use PDO; // Ajout de l'importation du namespace PDO
 
class SingletonDB {
 
   /**
    * @var SingletonDB
    * @access private
    * @static
    */
   private static $_instance = null;
   private $pdo;
 
   /**
    * Constructeur de la classe
    *
    * @param void
    * @return void
    */
   private function __construct() {  
      // Utilisation de $this->pdo pour référencer la propriété
      $this->pdo = new PDO("mysql:host=db;dbname=tp;charset=utf8mb4", "root", "root");
   }
   
 
   public function getPDO() {
    return $this->pdo;
   }
 
 
   /**
    * Méthode qui crée l'unique instance de la classe
    * si elle n'existe pas encore puis la retourne.
    *
    * @param void
    * @return SingletonDB
    */
   public static function getInstance() {
 
     if(is_null(self::$_instance)) {
       self::$_instance = new SingletonDB();  
     }
 
     return self::$_instance;
   }
}
 
?>