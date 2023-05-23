# Geocaching GeoJSON Generator

Create GeoJSON files of visited and unvisited counties. Can be used e.g. in c:geo to help visiting new counties.

Supported data sources for the visited counties:
- https://project-gc.com/Tools/MapCounties?country=Germany&submit=Filter
- https://project-gc.com/Challenges/GCA0QAH/71987
- https://project-gc.com/Statistics/ProfileStats#FTF

## Usage:
- Clone repo
- Put content from one of the 3 mentioned sources as *input.txt*
- Run *python geojson_counties.py*