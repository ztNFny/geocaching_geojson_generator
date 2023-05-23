#!/usr/bin/python3
import json
import csv
import re

basefolder = './'
inputFileName = 'input.txt'

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

inputType = ''

with open(basefolder+inputFileName, newline='', encoding='utf-8') as f:
	firstline = f.readline()
	if re.match('^[\d\t-]*Germany [^/]+ / ([^\t]+)\tGC', firstline):
		inputType = 'FTF'
	elif re.match('^[^/]+ / (.+) - D\d\.\d/T\d\.\d', firstline):
		inputType = 'T5'
	elif re.match('^[^\t]*\t\d*\t[^\t]*\t\d*\t[^\t]*\t\d*\t[^\t]*\t\d*$', firstline):
		inputType = 'MapCounties'
	else:
		print("Unrecognized input format")
		exit(1)

if inputType == 'FTF':
	# Parse output of https://project-gc.com/Statistics/ProfileStats#FTF
	with open(basefolder+inputFileName, newline='', encoding='utf-8') as f:
		while line := f.readline():
			z = re.match('^[\d\t-]*Germany [^/]+ / ([^\t]+)\tGC', line)
			if z:
				lkname = z.group(1)
				foundCounties.append(z.group(1))
elif inputType == 'T5':
	# Parse output of https://project-gc.com/Challenges/GCA0QAH/71987
	with open(basefolder+inputFileName, newline='', encoding='utf-8') as f:
		while line := f.readline():
			lkname = re.match('^[^/]+ / (.+) - D\d\.\d/T\d\.\d', line).group(1)
			foundCounties.append(lkname)
elif inputType == 'MapCounties':
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
