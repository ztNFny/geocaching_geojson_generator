import re
import requests
import tempfile

basefolder = './'
inputFileName = 'input.html'
country = 'Luxembourg' # used for output filename and PGC
level = 'regions' # used for output filename and PGC
level = 'counties' # used for output filename and PGC

sourceType = 'PGC'
#sourceType = 'file'
pgcUsername = 'test'

if sourceType == 'PGC':
	from gcapis import ProjectGC

def main():
	if sourceType == 'PGC':
		inFile = getPGCGeoJson(pgcUsername, country, level == 'regions')
	else:
		inFile = basefolder + '/' + inputFileName
	print(inFile)
	createGeoJson(inFile, basefolder + '/' +country + '_' + level + '.geojson')

def getPGCGeoJson(username: str, country: str, isRegions: bool):
	if isRegions:
		out = ProjectGC(username).pgcGetMapRegionsHtml(country)
	else:
		out = ProjectGC(username).pgcGetMapCountiesHtml(country)
	tmpout = tempfile.gettempdir() + '/' + country + '.html'
	with open(tmpout, 'w', encoding='utf-8') as f:
		f.write(out)
	return tmpout

def createGeoJson(inputFile: str, outputFile: str):
	out = '{ "type": "FeatureCollection", "crs": { "type": "name", "properties": { "name": "urn:ogc:def:crs:OGC:1.3:CRS84" } }, "features": ['

	isFirst = True

	with open(inputFile, newline='', encoding='utf-8') as f:
		regionName = ''
		geojson = ''
		while line := f.readline():
			z = re.match('.*new L\.geoJson\(JSON.parse\(\'(.*)\'\), \{', line)
			if z:
				geojson = z.group(1)
			z = re.match('.*polygon.bindPopup.*submit=Filter&(county|region)=([^"]*)">.*', line)
			if z:
				regionName = z.group(2)
				if not isFirst:
					out += ','
				out += '{ "type": "Feature", "properties" : { "GEN": "'+ regionName +'" }, "geometry": ' + geojson + ' }'
				isFirst = False

		out += ']}'

	with open(outputFile, 'w', encoding='utf-8') as f:
		f.write(out)
	
	print("GeoJson written to " + outputFile)

main()