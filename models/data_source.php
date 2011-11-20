<?php
define('DEBUG', false);

class DataSource{
	var $host = '';
	var $db_name = '';
	var $user = '';
	var $password = '';
	var $connection = null;

	function __construct($db_settings){
		$this->host = $db_settings['host'];
		$this->db_name = $db_settings['db_name'];
		$this->user = $db_settings['user'];
		$this->password = $db_settings['password'];

		$this->connection = mysql_connect($this->host, $this->user, $this->password) or die('Could not connect: ' . mysql_error($this->connection));
		if(DEBUG) echo __METHOD__." connected to {$this->host}.<br/>";
			
		mysql_select_db($this->db_name, $this->connection) or die('Could not select database');
		if(DEBUG) echo __METHOD__." Database Selected: {$this->db_name}.<br/>";
	}

	function __destruct(){
		if($this->connection) mysql_close($this->connection);
	}

	function Query($sql){
		if(DEBUG) echo __METHOD__." Running: {$sql}.<br/>";
		$dataset = array();
			
		mysql_select_db($this->db_name, $this->connection) or die('Could not select database');
		$result = mysql_query($sql, $this->connection) or die('['.__METHOD__.'::'.__LINE__.'] Query failed: ' . mysql_error($this->connection));
		while($rs = mysql_fetch_array($result, MYSQL_ASSOC)){
			$dataset[] = $rs;
		}
			
		if(DEBUG) echo __METHOD__." Results:".print_r($dataset, true)."<br/>";
		return $dataset;
	}

	function Execute($sql){
		if(DEBUG) echo __METHOD__." Running: {$sql}<br/>.";
			
		mysql_select_db($this->db_name, $this->connection) or die('Could not select database');
		$result = mysql_query($sql, $this->connection) or die('['.__METHOD__.'::'.__LINE__.'] Query failed: ' . mysql_error($this->connection));

		if(DEBUG) echo __METHOD__." Last Inserted Id: ".mysql_insert_id($this->connection).", Rows Affected:".mysql_affected_rows($this->connection)."<br/>";
		return array('id'=>mysql_insert_id($this->connection), 'rows_affected'=>mysql_affected_rows($this->connection));
	}

	function escape_string($string){
		$result = $string;
		if($string){
			$result = mysql_real_escape_string($string, $this->connection);
		}
		return $result;
	}
}
?>