<?PHP

/* ==================  */
/* You may modify here */
/* ==================  */
$FTP_HOST = "ftp.backup.com";
$FTP_USERNAME = "ftpuser";
$FTP_PASSWORD = "ftppass";
$FTP_DIR = "/backups/";

$del_m = 1; 	/* Delete files x months older */
$del_d = 0;		/* Delete files x days older */
				/* m = 1 and d = 5 will delete files older than 1 month & 5 days */

/* ================================== */
/* NO MODIFICATIONS BEYOND THIS POINT */
/* ================================== */
$today = mktime(0,0,0,date("n"),date("j"),date("Y"),0);
$delafter = mktime(0,0,0,1+$del_m,1+$del_d,1970,0);
$filename = "FTPfList.txt";
$fdellist = "FTPdelList.txt";
$output_messages = array();
$conn_id = ftp_connect($FTP_HOST);
$login_result = ftp_login($conn_id, $FTP_USERNAME, $FTP_PASSWORD);
ftp_pasv($conn_id, true);
ftp_chdir($conn_id,$FTP_DIR);
$contents = ftp_nlist($conn_id,".");
$handle = fopen($filename,'w') or die("can't open file");
foreach ($contents as $fname)
	fwrite($handle,$fname."\n");
fclose($handle);

$lines = file($filename);
$handle = fopen($fdellist,'w') or die("can't open file");
foreach ($lines as $line) {
	$tmp = explode("-",$line);
	if(count($tmp) == 3) {
		$mm = $tmp[0];
		$dd = $tmp[1];
		$yy = substr($tmp[2],0,4);
		$epochd = mktime(0,0,0,$mm,$dd,$yy,0);
		$ddiff = $today - $epochd;
		//echo "$today - $epochd = $ddiff <br />";
		if ($ddiff > $delafter){
			fwrite($handle,$line);		
		//echo "$dd/$mm/$yy = $epochd"."<br />";
		}
	}
}
fclose($handle);

$lines = file($fdellist);
foreach ($lines as $line) {
	ftp_chdir($conn_id,trim($line));
	$fdele = ftp_nlist($conn_id,".");
	if($fdele){
		foreach ($fdele as $f_del)
			@ftp_delete($conn_id, trim($f_del));
	}
	ftp_cdup($conn_id);
	if(ftp_rmdir($conn_id,trim($line)))
		echo "Directory $line Deleted Successfully<br />";
	else
		echo "Unable to delete directory : $line !<br />";
}


ftp_close  ( $conn_id );
unset($FTP_HOST,$FTP_USERNAME,$FTP_PASSWORD,$FTP_DIR,$filename,$fdellist,$conn_id,$login_result,$contents,$handle,$fname,$fpath,$lines,$line,$tmp,$fdate,$dd,$mm,$yy,$epochd,$ddiff,$today,$f_del);

?>