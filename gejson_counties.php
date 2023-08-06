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
<form method='POST' action=''>
<h2>County info</h2>
<h3>Data Sources</h3>
<ul>
<li><a href='https://project-gc.com/Challenges/GC90TVE/56012'>Counties</a></li>
<li><a href='https://project-gc.com/Tools/MapCounties?country=Germany'>Counties (alternative)</a></li>
</ul>
<p>Input from Project-GC. Paste ONLY lines containing the actual county information, NO HEADINGS!</p>
<p><textarea name='input' rows='10' cols='150'></textarea></p>
<h2>Additional info (T5 OR FTF)</h2>
<h3>Data Sources</h3>
<ul>
<li><a href='https://project-gc.com/Statistics/ProfileStats#FTF'>FTF</a></li>
<li><a href='https://project-gc.com/Challenges/GCA0QAH/71987'>T5</a></li>
</ul>
<p>Input from Project-GC. Paste ONLY lines containing the actual county information, NO HEADINGS!</p>
<p><textarea name='input2' rows='10' cols='150'></textarea></p>
<h2>Colors</h2>
<p><label for='color_found'>Found:</label> <input type='color' name='color_found' id='color_found' value='#116611' /></p>
<p><label for='color_notfound'>Not found:</label> <input type='color' name='color_notfound' id='color_notfound' value='#ff3333' /></p>
<p><label for='color_special'>FTF / T5:</label> <input type='color' name='color_special' id='color_special' value='#3333ff' /></p>
<p><input type='submit' /></p>
</form>
</body>
</html>";
}

function identifySource($firstline) {
	if (preg_match('/^[\d\t-]*Germany [^\/]+ \/ ([^\t]+)\tGC/', $firstline))
		$inputType = 'FTF';
	else if (preg_match('/^[^\/]+ \/ (.+) - D\d\.\d\/T\d\.\d/', $firstline))
		$inputType = 'T5';
	else if (preg_match('/^[\d\/]+\tGermany\t[^\t]+\t([^\t]+)\t\d*\.gif/', $firstline))
		$inputType = 'Counties';
	else if (preg_match('/^[^\t]*\t\d*\t[^\t]*\t\d*\t[^\t]*\t\d*\t[^\t]*\t\d*$/', $firstline))
		$inputType = 'MapCounties';
	else {
		$inputType = '';
	}
	return $inputType;
}

function buildArray($inputType, $input) {
	$foundCounties = [];
	$missingCounties = [];
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
	return $foundCounties;
}

function generate() {
	$basefolder = '';
	$lk_js = json_decode(file_get_contents($basefolder.'landkreise_simplify200.geojson'), true);

	$counties = [];
	if (($fh = fopen($basefolder.'mapping.csv', "r")) !== FALSE) {
		while(($data = fgetcsv($fh, 1000, "\t")) !== FALSE) {
			$counties[$data[1].'_'.$data[2]] = $data[0];
		}
		fclose($fh);
	}

	$input = preg_split('/\r\n|[\r\n]/', $_POST['input']);
	$inputType = identifySource($input[0]);
	$foundCounties = buildArray($inputType, $input);

	$input2 = preg_split('/\r\n|[\r\n]/', $_POST['input2']);
	$input2Type = identifySource($input2[0]);
	$specialCounties = buildArray($input2Type, $input2);

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

	$out = [];

	$features = $lk_js["features"];
	foreach($features as $county) {
		if (isset($county["properties"]) && isset($county["properties"]["GEN"])) {
			$ckey = $county["properties"]["GEN"].'_'.$county["properties"]["BEZ"];
			if (array_key_exists($ckey, $counties)) {
				$pgcname = $counties[$ckey];
				$color = $_POST['color_notfound'];
				if (in_array($pgcname, $specialCounties)) {
					$color = $_POST['color_special'];
				} else if (in_array($pgcname, $foundCounties)) {
					$color = $_POST['color_found'];
				}
				$county["properties"]['stroke-opacity'] = 0.6;
				$county["properties"]['fill-opacity'] = 0.2;
				$county["properties"]['fill'] = $color;
				$county["properties"]['stroke'] = $color;
				$county["properties"]['stroke-width'] = 2;
				array_push($out, $county);
			} else {
				echo "Unmapped country: ".$ckey;
			}
		}
	}

	$newjsonArray["features"] = $out;
	header('Content-Type: application/geo+json');
	header('Content-disposition: attachment; filename="stats_'.$input2Type.'.geojson"');
	print(json_encode($newjsonArray));
}
?>