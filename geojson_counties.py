#!/usr/bin/python3
import json
import csv
import requests
import re

basefolder = './'

inputFileName = 'input_mapcounties.txt'
#inputFileName = 'input_t5.txt'
inputIsT5 = False

with open(basefolder+'landkreise_simplify200.geojson', newline='', encoding='utf-8') as f:
	js = json.load(f)

counties = {}
foundCounties = []
missingCounties = []

# create mapping between GeoJson and Project-GC county names
with open(basefolder+'mapping.csv', newline='', encoding='utf-8') as f:
	reader = csv.reader(f, delimiter='\t')
	for row in reader:
		counties[row[1] + '_' + row[2]] = row[0]

if inputIsT5:
	# Parse output of https://project-gc.com/Challenges/GCA0QAH/71987
	with open(basefolder+inputFileName, newline='', encoding='utf-8') as f:
		while line := f.readline():
			lkname = re.match('^[^/]+ / (.+) - D\d\.\d/T\d\.\d', line).group(1)
			foundCounties.append(lkname)
else:
	# Parse output of https://project-gc.com/Tools/MapCounties?country=Germany&submit=Filter
	with open(basefolder+inputFileName, newline='', encoding='utf-8') as f:
		reader = csv.reader(f, delimiter='\t')
		for row in reader:
			for i in [0, 2, 4, 6]:
				lkname = row[i]
				if row[i+1] != '0':
					foundCounties.append(lkname)
				else:
					missingCounties.append(lkname)

newjson = {
	'type': 'FeatureCollection',
	'crs': {
		'type': 'name',
		'properties': { 'name': 'urn:ogc:def:crs:OGC:1.3:CRS84' }
	},
	'features': []
}

found = []
notFound = []

features = js['features']
for county in features:
	if 'properties' in county and 'GEN' in county['properties']:
		ckey = county['properties']['GEN'] + '_' + county['properties']['BEZ']
		if ckey in counties:
			pgcname = counties.get(ckey)
			if pgcname in foundCounties:
				found.append(county)
			else:
				notFound.append(county)
		else:
			print("Unmapped country: " + ckey)

# Write Founds file
newjson['features'] = found
with open(basefolder + 'counties' + '_found' + '.geojson', 'w', encoding='utf-8') as f:
	json.dump(newjson, f)

# Write Missing file
newjson['features'] = notFound
with open(basefolder + 'counties' + '_missing' + '.geojson', 'w', encoding='utf-8') as f:
	json.dump(newjson, f)
