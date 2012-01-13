<?php

/* =============*/
/* Modify here */
/* ============*/

$hosts = array("mysql.host1.com","mysql.host2.com","host3.com");
$usernames = array("user1","user2","user3");
$passwords = array("password1","password2","password3");

$FTP_HOST = "ftp.backup.com";
$FTP_USERNAME = "ftpuser";
$FTP_PASSWORD = "ftppass";
$FTP_DIR = "/backups/";

/* ================================================== */
/* DON'T MAKE ANY MODIFICATIONS BELOW THIS POINT !!!! */
/* ================================================== */
$cnt = count($hosts);
if( count($usernames) < $cnt || count($passwords) < $cnt )
	exit("\nHostname or Username or Password missing in the defined array.");

require("Zip.inc.php");
set_time_limit(0);
$output_messages=array();
$handle=0;
$conn_id = 0;
$file_list=array();
$i=0;
$dd = date('d');
$mm = date('m');
$yy = date('Y');
$tm = date('Hi');
$backup_dir = $mm."-".$dd."-".$yy."_".$tm;
if(!is_dir($backup_dir))
	mkdir($backup_dir) or die("Could not create directory");

$conn_id = @ftp_connect($FTP_HOST);
	if ($conn_id){
		$login_result = @ftp_login($conn_id, $FTP_USERNAME, $FTP_PASSWORD);
		if($login_result)
			ftp_pasv($conn_id, true);
		else{
			die("\nFTP Login Failed!");
			ftp_close($conn_id);
			
		}
	}
	else
		die("\nFailed to resolve FTP Host!");

ftp_chdir($conn_id,$FTP_DIR);
@ftp_mkdir($conn_id,$backup_dir);
ftp_chdir($conn_id,$backup_dir);
foreach($hosts as $host)
{
	$output_messages = array();
	array_push($output_messages, "\n=====================================================");
	$dblist=GetDBList($hosts[$i],$usernames[$i],$passwords[$i]);
	$mysql_database = $dblist[1];
	GetDbase($hosts[$i],$mysql_database,$usernames[$i],$passwords[$i]);
	$z = new Archive_Zip("$backup_dir/$mysql_database.zip");
	if($z -> create("$backup_dir/$mysql_database.sql")){
		array_push($output_messages, "\nArchive Created Successfully ($mysql_database.zip)");
		unlink("$backup_dir/$mysql_database.sql");
	if(FTPUpload("$backup_dir/$mysql_database.zip"))
		unlink("$backup_dir/$mysql_database.zip");
	}
	else
		array_push($output_messages, "\nFailed Creating Archive : $mysql_database.zip !");
	
	array_push($output_messages, "\n=====================================================");
	foreach ($output_messages as $message)
	echo $message."<br />";
	$i++;
}

ftp_close($conn_id);
delete_directory($backup_dir);


function GetDBList($mysql_host,$mysql_username,$mysql_password)
{
	$conn = @mysql_connect($mysql_host,$mysql_username,$mysql_password) or die( mysql_errno().': '.mysql_error()."\n");
	$result = mysql_list_dbs($conn);
	while( $row = mysql_fetch_object( $result ) ):
	$dblist[]=$row->Database;
	endwhile;
	mysql_free_result($result);
	mysql_close($conn);
	return $dblist;
}

function GetDbase($mysql_host,$mysql_database,$mysql_username,$mysql_password)
{
	global $handle,$output_messages,$backup_dir;
	$filename = "$backup_dir/$mysql_database.sql";
	_mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password);
	$handle = fopen($filename,'w') or die("can't open file");
	fwrite($handle,"/* MySQLDumper.php version 1.0 */\n");
	_mysqldump($mysql_database);
	fclose($handle);
}

function FTPUpload($zipfilename)
{
	global $FTP_DIR,$conn_id,$output_messages;
	$upload = ftp_put($conn_id,$FTP_DIR.$zipfilename,$zipfilename,FTP_BINARY);
	if($upload){
		array_push($output_messages, "\nFile Uploaded Sucessfully");
		return 1;
		}
		else{
		array_push($output_messages, "\nFTP File Upload Failed!");
		return 0;
		}
	}

function delete_directory($dirname) {
	if (is_dir($dirname))
		$dir_handle = opendir($dirname);
	if (!$dir_handle)
		return false;
	while($file = readdir($dir_handle)) {
		if ($file != "." && $file != "..") {
			if (!is_dir($dirname."/".$file))
				unlink($dirname."/".$file);
			else
				delete_directory($dirname.'/'.$file);          
		}
	}
	closedir($dir_handle);
	rmdir($dirname);
	return true;
}

function _mysqldump($mysql_database)
{
global $handle;
	$sql="show tables;";
	$result= mysql_query($sql);
	if( $result)
	{
		while( $row= mysql_fetch_row($result))
		{
		_mysqldump_table_structure($row[0]);
		_mysqldump_table_data($row[0]);
		}
	}
	else
	{
		fwrite($handle,"/* No tables in $mysql_database */\n");
	}
	mysql_free_result($result);
}

function _mysqldump_table_structure($table)
{
global $handle;
	fwrite($handle,"/* Table structure for table `$table` */\n");
	fwrite($handle,"DROP TABLE IF EXISTS `$table`;\n\n");
	$sql="show create table `$table`; ";
	$result=mysql_query($sql);
	if( $result)
	{
		if($row= mysql_fetch_assoc($result))
		{
			fwrite($handle,$row['Create Table'].";\n\n");
		}
	}
	mysql_free_result($result);
}

function _mysqldump_table_data($table)
{
global $handle;
	$sql="select * from `$table`;";
	$result=mysql_query($sql);
	if( $result)
	{
		$num_rows= mysql_num_rows($result);
		$num_fields= mysql_num_fields($result);

		if( $num_rows > 0)
		{
			fwrite($handle,"/* Dumping data for table `$table` */\n");

			$field_type=array();
			$i=0;
			while( $i < $num_fields)
			{
				$meta= mysql_fetch_field($result, $i);
				array_push($field_type, $meta->type);
				$i++;
			}

			fwrite($handle,"insert into `$table` values\n");
			$index=0;
			while( $row= mysql_fetch_row($result))
			{
				fwrite($handle,"(");
				for( $i=0; $i < $num_fields; $i++)
				{
					if( is_null( $row[$i]))
						fwrite($handle,"null");
					else
					{
						switch( $field_type[$i])
						{
							case 'int':
								fwrite($handle,$row[$i]);
								break;
							case 'string':
							case 'blob' :
							default:
								fwrite($handle,"'".mysql_real_escape_string($row[$i])."'");

						}
					}
					if( $i < $num_fields-1)
						fwrite($handle,",");
				}
				fwrite($handle,")");

				if( $index < $num_rows-1)
					fwrite($handle,",");
				else
					fwrite($handle,";");
				fwrite($handle,"\n");

				$index++;
			}
		}
	}
	mysql_free_result($result);
	fwrite($handle,"\n");
}

function _mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password)
{
	global $output_messages,$dbaselist;
	$link = mysql_connect($mysql_host, $mysql_username, $mysql_password);
	if (!$link)
	{
	   array_push($output_messages, 'Could not connect: ' . mysql_error());
	}
	else
	{
		$dbaselist = mysql_list_dbs($link);
		$db_selected = mysql_select_db($mysql_database, $link);
		if (!$db_selected)
		{
			array_push ($output_messages,'\nCan\'t use $mysql_database : ' . mysql_error());
		}
		else
			array_push ($output_messages,"\nMySQL database: $mysql_database \n\n");
	}
}

?>
