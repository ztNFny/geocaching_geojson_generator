<?php

if (isset($_POST['input'])) {
	generate();
} else {
	inputForm();
}

function inputForm() {
print "<html>
<head>
<title>GeoJson generator</title>
</head>
<body>
<p>Generate GeoJson files for (not) found counties for GERMANY ONLY!</p>
<h3>Data Sources</h3>
<ul>
<li><a href='https://project-gc.com/Statistics/ProfileStats#FTF'>FTF</a></li>
<li><a href='https://project-gc.com/Challenges/GCA0QAH/71987'>T5</a></li>
<li><a href='https://project-gc.com/Challenges/GC90TVE/56012'>Counties</a> / <a href='https://project-gc.com/Tools/MapCounties?country=Germany'>Counties (alternative)</a></li>
</ul>
<form method='POST' action=''>
<p>Generate GeoJson for Counties ...<br/>
<input type='radio' name='type' id='type_found' value='found' checked='checked' /><label for='type_found'>Found</label>
<input type='radio' name='type' id='type_notfound' value='notfound' /><label for='type_notfound'>Not Found</label>
</p>
<p>Input from Project-GC. Paste ONLY lines containing the actual county information, NO HEADINGS!</p>
<p><textarea name='input' rows='20' cols='150'></textarea></p>
<p><input type='submit' /></p>
</form>
</body>
</html>";
}

function generate() {
	$basefolder = '';
	$lk_js = json_decode(file_get_contents($basefolder.'landkreise_simplify200.geojson'), true);

	$counties = [];
	$foundCounties = [];
	$missingCounties = [];

	if (($fh = fopen($basefolder.'mapping.csv', "r")) !== FALSE) {
		while(($data = fgetcsv($fh, 1000, "\t")) !== FALSE) {
			$counties[$data[1].'_'.$data[2]] = $data[0];
		}
		fclose($fh);
	}

	$input = preg_split('/\r\n|[\r\n]/', $_POST['input']);
	
	$firstline = $input[0];
	if (preg_match('/^[\d\t-]*Germany [^\/]+ \/ ([^\t]+)\tGC/', $firstline))
		$inputType = 'FTF';
	else if (preg_match('/^[^\/]+ \/ (.+) - D\d\.\d\/T\d\.\d/', $firstline))
		$inputType = 'T5';
	else if (preg_match('/^[\d\/]+\tGermany\t[^\t]+\t([^\t]+)\t\d*\.gif/', $firstline))
		$inputType = 'Counties';
	else if (preg_match('/^[^\t]*\t\d*\t[^\t]*\t\d*\t[^\t]*\t\d*\t[^\t]*\t\d*$/', $firstline))
		$inputType = 'MapCounties';
	else {
		echo "Unrecognized input format";
		exit(1);
	}

	foreach($input as $line) {
		if ($inputType == 'FTF' && preg_match("/^[\d\t-]*Germany [^\/]+ \/ ([^\t]+)\tGC/", $line, $out)) {
			array_push($foundCounties, $out[1]);
		} else if ($inputType == 'T5' && preg_match("/^[^\/]+ \/ (.+) - D\d\.\d\/T\d\.\d/", $line, $out)) {
			array_push($foundCounties, $out[1]);
		} else if ($inputType == 'Counties' && preg_match("/^[\d\/]+\tGermany\t[^\t]+\t([^\t]+)\t\d*\.gif/", $line, $out)) {
			array_push($foundCounties, $out[1]);
		} else if ($inputType == 'MapCounties') {
			$s = explode("\t", $line);
			for ($i = 0; $i < count($s) / 2; $i++) {				
				if ($s[$i+1] != 0)
					array_push($foundCounties, $s[$i]);
				else
					array_push($missingCounties, $s[$i]);
			}
		}
	}

	$newjsonArray = [
			"type" => "FeatureCollection",
			"crs" => [
					"type" => "name",
					"properties" => [
							"name" => "urn:ogc:def:crs:OGC:1.3:CRS84"
					]
			],
			"features" => []
	]; 

	$found = [];
	$notFound = [];

	$features = $lk_js["features"];
	foreach($features as $county) {
		if (isset($county["properties"]) && isset($county["properties"]["GEN"])) {
			$ckey = $county["properties"]["GEN"].'_'.$county["properties"]["BEZ"];
			if (array_key_exists($ckey, $counties)) {
				$pgcname = $counties[$ckey];
				if (in_array($pgcname, $foundCounties))
					array_push($found, $county);
				else
					array_push($notFound, $county);
			} else {
				echo "Unmapped country: ".$ckey;
			}
		}
	}

	if ($_POST['type'] == 'found') {
		$newjsonArray["features"] = $found;
	} else {
		$newjsonArray["features"] = $notFound;
	}
	header('Content-Type: application/geo+json');
	header('Content-disposition: attachment; filename="stats_'.$inputType.'_'.$_POST['type'].'.geojson"');
	print(json_encode($newjsonArray));
}
?>