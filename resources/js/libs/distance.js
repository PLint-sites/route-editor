const getDistanceOnSphere = (p1, p2) => {
    // Convert latitude and longitude to
    // spherical coordinates in radians.
    const degrees_to_radians = Math.PI/180

    // phi = 90 - latitude
    const phi1 = (90.0 - p1.lat) * degrees_to_radians
    const phi2 = (90.0 - p2.lat) * degrees_to_radians

    // theta = longitude
    const theta1 = p1.lng * degrees_to_radians
    const theta2 = p2.lng * degrees_to_radians

    /*
    # Compute spherical distance from spherical coordinates.

    # For two locations in spherical coordinates
    # (1, theta, phi) and (1, theta, phi)
    # cosine( arc length ) =
    #    sin phi sin phi' cos(theta-theta') + cos phi cos phi'
    # distance = rho * arc length
    */

    let cos = (Math.sin(phi1) * Math.sin(phi2) * Math.cos(theta1 - theta2) + Math.cos(phi1) * Math.cos(phi2));

    // dit is misschien/waarschijnlijk nodig voor afronding
    if (cos > 1) {
        cos = 1
    }

    if (cos < -1) {
        cos = -1
    }

    let arc = Math.acos(cos)

    // Remember to multiply arc by the radius of the earth (6373 km)
    // in your favorite set of units to get length.
    return arc * 6373
}

const calculateRouteDistance = points => {
    const nPoints = points.length
    let distance = 0;

    for (let i = 1; i < nPoints; i++) {
        distance += getDistanceOnSphere(points[i-1], points[i])
    }

    return distance
}

// export { calculateRouteDistance as default}
export { getDistanceOnSphere as calculatePointToPointDistance, calculateRouteDistance as default}