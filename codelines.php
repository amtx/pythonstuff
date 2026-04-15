<?

include('./html2fpdf/functions.php');

include('./menue.php');

if(isset($_GET['LS'])){
	$LieferscheinNr=preg_replace('/[^0-9L]*/', '', $_GET['LS']);
	echo '<h1>Generate Kartonetiketten for '.$LieferscheinNr.'</h1>';
}else{
	$LieferscheinNr='L050172';
	echo '<form >
	LieferscheinNr <input type=text name="LS">
	<input type="submit" value="send">
	</form>';
	exit;
	
}

/*
 Das Kartonetiketten script holt die Lieferscheinpositionen direkt
 aus der Datenbank vom PC Kaufmann als CSV-Datei.
 */
$URL='http://192.168.1.22/pck/CSV.php?ABFDocErfNr='.$LieferscheinNr;
$csvdata = file_get_contents($URL);

if($csvdata==''){
	echo "Error getting data from DB - check apache is running on Matzes PC...<br>";
	echo "Open XAMP control center and start apache webserver";
	exit;
}

$rawdata = array_reverse(explode("\n", $csvdata));

echo '<pre>';

if($_SERVER['REMOTE_ADDR']=='192.168.1.48' && isset($_GET['debug'])){
	echo 'Hi Joe<br>';
	print_r($csvdata);
}
/*else{
	echo $_SERVER['REMOTE_ADDR']."<br>";
	
}*/

$pdffiles=[];
$VPE=[];
$mybg='#fff';

$VPEraw=explode("\n", trim(loadfile('./VPE.dat')));
foreach($VPEraw as $VPEline){
	$subdata=explode("\t", $VPEline);
	$VPE[$subdata[2]]=$subdata[0];
}

// print_r($VPE);exit;

foreach($rawdata as $rawline){
	if(strpos($rawline, "\t")!==false && strpos($rawline, 'KNLU')===false && strpos($rawline, 'FD')===false){
		if($mybg=='#fff')$mybg='#efe';else $mybg='#fff';
		echo '<div style="background:'.$mybg.';margin:8px;padding:6px;width:600px;">';
		$rawline_utf8=utf8_encode($rawline)."\n";
		$sd=explode("\t", $rawline_utf8);
		// echo $rawline_utf8."\n";
		echo "Artikelname: <b>".$sd[9].'</b><br>';
		echo "Menge: <b>".$sd[16].'</b><br>';
		$matches=[];
		if(strpos($sd[9], 'ml')!==false){
			preg_match('/ [0-9]+ml/', $sd[9], $matches);
		}
		if(isset($matches[0])){
			echo 'found ml<br>';
		}else if(strpos($sd[9], 'Stück')!==false){
			preg_match('/ [0-9]+ Stück/', $sd[9], $matches);
		}else{
			preg_match('/ [0-9]+g/', $sd[9], $matches);
		}
		echo "found VPE: ".$matches[0]."\n";
		$ArtikelName = str_replace($matches[0], '', $sd[9]);
		$drops=[' "blau"', ' "grau"', '"rot"', '"grün"'];
		foreach($drops as $d){
			$ArtikelName = str_replace($d, '', $ArtikelName);
		}
		$ArtikelNummer = $sd[7];
		$Menge=intval($sd[16]);
		if(isset($VPE[$ArtikelNummer])){
			$prokarton=$VPE[$ArtikelNummer];
		}else{
			$prokarton=6;
		}
		echo 'pro Karton: '.$prokarton.'<br>';
		genKartonEtikett($ArtikelNummer, $ArtikelName, $prokarton, $matches[0]);
		$x=$Menge;
		while($x>0){
			$pdffiles[]='label/Karton/Karton_'.$ArtikelNummer.'.pdf';
			$x=$x-$prokarton;
		}
		if(file_exists('label/Karton/Karton_'.$ArtikelNummer.'.pdf')){
			echo '<a href="label/Karton/Karton_'.$ArtikelNummer.'.pdf">label/Karton/Karton_'.$ArtikelNummer.'.pdf</a><br>';
		}else{
			echo '<p style="color: red;">Error generating Label for '.$ArtikelNummer.'</p>';
		}
		echo '</div>';
	}
}
$CMD = 'pdfunite '.implode(' ',$pdffiles).' label/Karton/'.$LieferscheinNr.'.pdf';
// echo $CMD.'<br>';
system($CMD);
echo '<a href="label/Karton/'.$LieferscheinNr.'.pdf">label/Karton/'.$LieferscheinNr.'.pdf</a><br>';

function genKartonEtikett($ArtikelNummer, $ArtikelName, $Menge, $VPE){
	// $ArtikelName="Chillischoten getr.";
	// $ArtikelNummer='8302';
	// 0 is last day of previous month
	
	// $MHD=mktime(0, 0, 0, date('n') + 19, 0, date('Y')); // h m s d m y
	// we want one day more
	$datetime = new DateTime();
	$datetime->modify('+1 day');
	$MOT = $datetime->format('n'); // month of tomorrow
	$YOT = $datetime->format('Y'); // year of tomorrow
	$MHD=mktime(0, 0, 0, $MOT + 19, 0, $YOT);
	
	// $Menge=6;
	// $VPE='30g';
	$EANCode=genEANCode($ArtikelNummer);

	echo 'Generate Kartonetikett for ArtikelNummer: '.$ArtikelNummer.'<br>';
	$TEMPLATEFILENAME='./label/svgtemplates/Kartonetikett-105x74.svg';
	echo "READ Template<br>";
	$content=loadfile($TEMPLATEFILENAME);

	$content=str_replace('#ArtikelName#', $ArtikelName, $content);
	$content=str_replace('#M#', $Menge, $content);
	$content=str_replace('#VPE#', $VPE, $content);
	$content=str_replace('#MHD#', Date('d.m.Y', $MHD), $content);
	// $content=str_replace('#EAN#', $EANCode, $content);

	$BCFRAME='_KARTONETIKETT';

	/* svg barcode via python */
	if(!file_exists('./barcode/svg/ean13_'.$EANCode.'.svg')){
		$CMD = '/usr/bin/python3.5 ./barcode/svg/genean13svg.py '.trim($EANCode);
		system($CMD);
	}
	if(!file_exists('./barcode/svg/ean13_'.$EANCode.'.svg')){
		echo '<font color=red>ERROR can\'t generate barcode file ./barcode/svg/ean13_'.$EANCode.'.svg</font><br>';
		echo '/usr/bin/python3 ./barcode/svg/genean13svg.py '.trim($EANCode);
		exit;
	}
	$barcodeframe=loadfile('./barcode/svg/barcodeframe'.$BCFRAME.'.svg');
	if(strpos($barcodeframe,'#!-- BARCODEGROUP --#')===false){
		echo 'using barcodeframe '.'./barcode/svg/barcodeframe'.$BCFRAME.'.svg<br>';
		echo 'templte error "#!-- BARCODEGROUP --#"" not found<br>';
		exit;
	}else{
		$content=str_replace('#!-- EAN13 --#',$barcodeframe,$content);
	}
	$barcodeSVG=loadfile('./barcode/svg/ean13_'.$EANCode.'.svg');
	if(strlen($barcodeSVG)==0){
		echo 'using generated BARCODEGROUP "./barcode/svg/ean13_'.$EANCode.'.svg"<br>';
		echo 'barcode SVG error: length=0<br>';
		exit;
	}
	$start=strpos($barcodeSVG, '<rect height="100%" style="fill:white" width="100%"/>')+53;
	$end=strpos($barcodeSVG, '</g>');
	$barcodeSVG=substr($barcodeSVG, $start,$end-$start);
	// echo $barcodeSVG;exit();

	$content=str_replace('#!-- BARCODEGROUP --#', $barcodeSVG, $content);
	$content=str_replace('#-00-#',substr($EANCode,0,1),$content);
	$content=str_replace('#-01-#',substr($EANCode,1,6),$content);
	$content=str_replace('#-02-#',substr($EANCode,7,6),$content);
	$content=str_replace("Courier 10 Pitch", "Courier New", $content);


	echo "Write Template<br>";
	writefile('label/Karton/Karton_Test.svg', $content);

	echo "convert to pdf<br>";
	$CMD='inkscape --file=label/Karton/Karton_Test.svg --without-gui --export-pdf=label/Karton/Karton_'.$ArtikelNummer.'.pdf';
	system($CMD);
	if(file_exists('label/Karton/Karton_'.$ArtikelNummer.'.pdf')){
		// echo "pdf OK ";
		echo "pdf Datum: " . date ("Y-m-d H:i:s", filemtime('label/Karton/Karton_'.$ArtikelNummer.'.pdf')).'<br>';
	}else{
		echo '<p style="color: red;">Error generating Label for '.$ArtikelNummer.' <br>> cmd: '.$CMD.'</p>';
	}
}

echo "OK ready bye...<br>";

function genEANCode($Artikelnummer){
	// EANCodes
	$EANCode='4041392';
	if(substr(strtoupper($Artikelnummer),0,2)=='PR'){
		// Produktionsrohstoffe PR****
		$EANCode.='4'.substr($Artikelnummer,2);
	}else if(substr(strtoupper($Artikelnummer),0,2)=='HL'){
		//Handelsartikel lose HL****
		$EANCode.='3'.substr($Artikelnummer,2);
	}else {
		$EANCode.=substr('00008'.$Artikelnummer,-5);
	}
	$EANCode=EANPruefziffer($EANCode);
	if(strlen($EANCode)!=13){$EANCode = '<font style="color:red;">ERROR: EANCode generation error for ArtNr <i>'.$Artikelnummer.'</i>. EAN nicht 13 stellig.</font>';}
	return $EANCode;
}

function EANPruefziffer($EAN){
	// 12 stellige Ausgangszahl
	//$EAN=498070070034;
	// rest muss immer auf null gesetzt werden
	$rest=0;
	// Hier wird die Zahl "abgetastet" und nach obigem schema aufaddiert
	for ($i=0;$i<12;$i++) {
	if ($i % 2 == 0) {
		$zahl=substr($EAN,$i,1)*1;
		$rest=$rest+$zahl;
	} else {
		$zahl=substr($EAN,$i,1)*3;
		$rest=$rest+$zahl;
	}
	}
	// Wenn $rest groesser als 100
	if ($rest > 100) {
	if (substr($rest,2,1) != 0) {
		$aufrund_1=substr($rest,0,1);
		$aufrund_2=substr($rest,1,1) + 1;
		$aufrund_3=0;
		$aufrund=$aufrund_1.$aufrund_2.$aufrund_3;
	} else {
		$aufrund=$rest;
	}
	} else {
	if (substr($rest,1,1) != 0) {
		$aufrund_1=substr($rest,0,1) + 1;
		$aufrund_2=0;
		$aufrund=$aufrund_1.$aufrund_2;
	} else {
		$aufrund=$rest;
	}
	}

	$pruefziffer=$aufrund-$rest;
	
	return $EAN.$pruefziffer;
	
}
