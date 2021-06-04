<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

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

        // Was om een startpunt te hebben: een eerste route die uitgelezen was uit GPX. Nu hebben we import en is dit nie meer nodig.
        // $file = public_path('routes/wktocht_2013.GPX');
        // if (file_exists($file)) {
        //     extract($this->readGpx($file, 'strava'));
        //     extract($this->addDistance($lats, $lons));

        //     // number of points per km is 'pointDensity'
        //     $pointDensity = ceil(count($lats)/$total_distance);
        //     $coarsenFactor = ceil($pointDensity/5);

        //     $coarsendData = [];
        //     foreach($latlng as $key => $point) {
        //         if ($key % $coarsenFactor == 0) {
        //             $coarsendData[] = $point;
        //         }
        //     }

        //     return Inertia::render('RouteCreator', ['track' => $coarsendData, 'distance' => $total_distance]);
        // } else {
        //     dd('file niet gevonden');
        // }
    }

    /**
     * Import existing GPX file. Check if correct filetype, open and read lat-lng data and return it
     *
     * @param Request $request
     * @return void
     */
    public function import(Request $request)
    {
        // available: $lats, $lons, $latlng
        extract($this->readGpx($request->gpx, 'strava'));
        extract($this->addDistance($lats, $lons));

        // number of points per km is 'pointDensity'
        $pointDensity = ceil(count($lats)/$total_distance);
        $coarsenFactor = ceil($pointDensity/5);

        $coarsendData = [];
        foreach($latlng as $key => $point) {
            if ($key % $coarsenFactor == 0) {
                $coarsendData[] = $point;
            }
        }

        return ['track' => $coarsendData, 'distance' => $total_distance];
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

    private function readGpx($gpx, $type)
    {
        switch ($type) {
            case 'strava':
                $data = $this->readStrava($gpx);
                break;
            case 'basecamp':
                $data = $this->readBasecamp($gpx);
                break;
        }
        return $data;
        
    }

    private function readStrava($gpx)
    {
        $xml = new XMLReader();

        $xml->open($gpx);

        $mytrack = ['data' => []];

        while ($xml->read()) {

            if ($xml->name == 'metadata') {
                // date
                $meta = new SimpleXMLElement($xml->readOuterXml());
                $date = new Carbon($meta->time);
                $mytrack['date'] = $date->format('l jS \\of F Y');
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

        // for computing distance
        $lats = array_map(function($point) {
            return $point['lat'];
        }, $mytrack['data']);

        $lons = array_map(function($point) {
            return $point['lon'];
        }, $mytrack['data']);

        // for plotting on the map
        $latlng = array_map(function($point) {
            return [$point['lat'], $point['lon']];
        }, $mytrack['data']);

        return compact('latlng', 'lats', 'lons');
    }
}
