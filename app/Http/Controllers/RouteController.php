<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

use XMLReader;
use SimpleXMLElement;
use Carbon\Carbon;
use domDocument;

class RouteController extends Controller
{
    /**
     * Route creator: start with a fixed file to import
     *
     * @return void
     */
    public function init()
    {
        return Inertia::render('RouteCreator');
    }

    /**
     * Import existing GPX file by file upload. Check if correct filetype, open and read lat-lng data and return it
     *
     * @param Request $request
     * @return Array
     */
    public function import(Request $request)
    {
        extract($this->readGpx($request->gpx));

        $route = $this->CoarsenDataAndAppendDistance($lats, $lons, $latlng, $needsCoarsening);
        $route['name'] = $name;

        return $route;
    }

    /**
     * Import existing file from storage
     *
     * @param Request $request
     * @return Array
     */
    public function importFromFilename(Request $request)
    {
        $filename = 'storage/base-routes/' . $request->file;
        if (file_exists($filename)) {
            extract($this->readGpxAsXml($filename));

            $route = $this->CoarsenDataAndAppendDistance($lats, $lons, $latlng, $needsCoarsening);
            $route['name'] = $request->file;

            return $route;
        }

        return response('File niet gevonden', 404);
    }

    /**
     * Export a track to a GPX file, downloaded instantly
     *
     * @param Request $request
     * @return void
     */
    public function export(Request $request)
    {
        $now = Carbon::now();

        $dom = new domDocument;
        $dom->formatOutput = true;
        
        $gpxElement = $dom->createElement('gpx');
        
        // verschillende verplichte(?) attributen toekennen
        $XmlnsAttr = $dom->createAttribute('xmlns');
        $XmlnsAttr->value = "http://www.topografix.com/GPX/1/1";
        $gpxElement->appendChild($XmlnsAttr);
        
        // gpxElement toevoegen aan Domnode
        $root = $dom->appendChild($gpxElement);
        
        $sxe = simplexml_import_dom($dom);

        $metadata = $sxe->addChild('metadata');
        $metadata->addChild('time', $now->toISOString());

        $track = $root->appendChild($dom->createElement('trk'));
        $track->appendChild($dom->createElement('name', 'Rondje 1'));
        $segment = $track->appendChild($dom->createElement('trkseg'));
        
        foreach ($request->data as $point) {
            $trkpoint = $dom->createElement('trkpt');

            $latAttr = $dom->createAttribute('lat');
            $latAttr->value = $point['lat'];
            $trkpoint->appendChild($latAttr);

            $lonAttr = $dom->createAttribute('lon');
            $lonAttr->value = $point['lng'];
            $trkpoint->appendChild($lonAttr);

            $segment->appendChild($trkpoint);
        }

        $dom = dom_import_simplexml($sxe)->ownerDocument;
        $dom->preserveWhiteSpace = false;
        $xmlfile = 'storage/routes/track_' .  $now->format('Ymd_His') . '.gpx';
        $dom->save($xmlfile);
    }

    // ---
    // Helper functions to read data, move to library
    // ---

    private function distanceOnUnitSphere($lat1, $long1, $lat2, $long2)
    {
        // Convert latitude and longitude to
        // spherical coordinates in radians.
        $degrees_to_radians = pi()/180.0;

        // phi = 90 - latitude
        $phi1 = (90.0 - $lat1)*$degrees_to_radians;
        $phi2 = (90.0 - $lat2)*$degrees_to_radians;

        // theta = longitude
        $theta1 = $long1*$degrees_to_radians;
        $theta2 = $long2*$degrees_to_radians;


        /*
        # Compute spherical distance from spherical coordinates.

        # For two locations in spherical coordinates
        # (1, theta, phi) and (1, theta, phi)
        # cosine( arc length ) =
        #    sin phi sin phi' cos(theta-theta') + cos phi cos phi'
        # distance = rho * arc length
        */

        $cos = (sin($phi1)*sin($phi2)*cos($theta1 - $theta2) + cos($phi1)*cos($phi2));

        // dit is misschien/waarschijnlijk nodig voor afronding
        if ($cos > 1) {
            $cos = 1;
        }

        if ($cos < -1) {
            $cos = -1;
        }

        $arc = acos($cos);

        // Remember to multiply arc by the radius of the earth (6373 km)
        // in your favorite set of units to get length.
        return $arc*6373;
    }

    /**
     * Calculate distance (array) and total distance (float) from LatLng series
     *
     * @param Array $lats
     * @param Array $lons
     * @return Array
     */
    private function addDistance($lats, $lons) {
        $distance = [0];
        $total_distance = 0;

        $nPoints = count($lats) - 1;

        for ($i = 0; $i < $nPoints; $i++) {
            $distance_segment = $this->distanceOnUnitSphere($lats[$i], $lons[$i], $lats[$i+1], $lons[$i+1]);
            $total_distance += $distance_segment;
            $distance[] = $total_distance;
        }

        return compact('distance', 'total_distance');
    }

    /**
     * Read data from uploaded GPX file
     *
     * @param Stream $uploadedFileStream
     * @return void
     */
    private function readGpx($uploadedFileStream)
    {
        $name = $this->getRouteName($uploadedFileStream);

        extract($this->readGpxAsXml($uploadedFileStream));

        return compact('lats', 'lons', 'latlng', 'needsCoarsening', 'name');
    }

    /**
     * Read data from GPX file on storage
     *
     * @param String $filename
     * @return void
     */
    private function readGpxAsXml($filename)
    {
        $xml = new XMLReader();

        $xml->open($filename);

        $mytrack = ['data' => []];

        while ($xml->read()) {

            if ($xml->name == 'metadata') {
                // date, link and name
                $meta = new SimpleXMLElement($xml->readOuterXml());
                $date = new Carbon($meta->time);
                $mytrack['date'] = $date->format('l jS \\of F Y');
                if (!empty($meta->name)) {
                    $name = (string)$meta->name;
                }
            } else if ($xml->name == 'trk') {
                $track = new SimpleXMLElement($xml->readOuterXml());

                foreach ($track as $key => $value) {
                    if ($key === 'name') {
                        $mytrack['name'] = (string)$value;
                    } else if ($key === 'trkseg') {
                        $mypoint = [];
                        $prevpoint = ['lat' => '-1', 'lon' => '-1'];
                        foreach ($value as $key2 => $point) {
                            $mypoint['lat'] = (string)$point->attributes()['lat'];
                            $mypoint['lon'] = (string)$point->attributes()['lon'];

                            if ($mypoint['lat'] != $prevpoint['lat'] && $mypoint['lon'] != $prevpoint['lon']) {
                                foreach ($point as $key3 => $data) {
                                    if ($key3 == 'ele') {
                                        $mypoint['ele'] = (string)$data;
                                    }

                                    if ($key3 == 'time') {
                                        $mypoint['date'] = New Carbon($data);
                                    }
                                }
                                $mytrack['data'][] = $mypoint;
                                $prevpoint = $mypoint;
                            }
                        }
                    }
                }
            }
        }

        // check needsCoarsening again based on number of points
        $needsCoarsening = false;
        if (count($mytrack['data']) > 500) $needsCoarsening = true;

        // for computing distance
        $lats = array_map(function($point) {
            return $point['lat'];
        }, $mytrack['data']);

        $lons = array_map(function($point) {
            return $point['lon'];
        }, $mytrack['data']);

        $latlng = array_map(function($point) {
            return [$point['lat'], $point['lon']];
        }, $mytrack['data']);

        return compact('lats', 'lons', 'latlng', 'needsCoarsening');
    }

    /**
     * Add distance and apply coarsening
     *
     * @param Array $lats
     * @param Array $lons
     * @param Array $latlng
     * @param Boolean $needsCoarsening
     * @return Array
     */
    private function CoarsenDataAndAppendDistance($lats, $lons, $latlng, $needsCoarsening)
    {
        // $distance, $total_distance
        extract($this->addDistance($lats, $lons));

        $coarsenFactor = 1;
        if ($needsCoarsening) {
            // number of points per km is 'pointDensity'
            $pointDensity = ceil(count($lats)/$total_distance);
            $coarsenFactor = ceil($pointDensity/5);
        }

        $coarsendData = [];
        foreach($latlng as $key => $point) {
            if ($key % $coarsenFactor == 0) {
                $coarsendData[] = $point;
            }
        }

        return [
            'track' => $coarsendData,
            'distance' => $total_distance
        ];
    }

    /**
     * Determine name of track based on uploaded file original name
     *
     * @param Stream $uploadedFile
     * @return String
     */
    private function getRouteName($uploadedFile)
    {
        $name = $uploadedFile->getClientOriginalName();

        if (Str::endsWith($name, ['.gpx', '.GPX'])) {
            return Str::substr($name, 0, strlen($name)-4);
        }

        return $name;
    }
}
