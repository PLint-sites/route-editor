const calculateSquareMatrixPosition = (point, dx, dy, startPoint) => {
    const x = point[1]
    const y = point[0]
    const x0 = startPoint[1]
    const y0 = startPoint[0]

    return {
        row: Math.ceil((y-y0) / dy),
        column: Math.ceil((x - x0) / dx)
    }
}

const calculateSquareIndex = (row, column, nCols) => {
    return (row - 1) * nCols + (column -1)
}

const createGridPositions = (centerPoint, deltaLat, deltaLng, nPoints) => {
    const grid = []
    for (let i = 0; i < nPoints; i++) {
        const bottomLat = (-nPoints * deltaLat / 2) + centerPoint[0] + deltaLat * i 
        for (let j = 0; j < nPoints; j++) {
            const leftLng = (-nPoints * deltaLng / 2) + centerPoint[1] + deltaLng * j 

            const gridPoint = {
                index: j + nPoints * i,
                ar: [bottomLat, leftLng],
                lat: bottomLat,
                lng: leftLng,
                count: 0
            }

            // grid.push([bottomLat, leftLng])
            grid.push(gridPoint)
        }
    }

    return grid
}

const createLeafletGridSquares = (centerPoint, deltaLat, deltaLng, nPoints) => {
    const grid = []
    for (let i = 0; i < nPoints; i++) {
        const topLat = (-nPoints * deltaLat / 2) + centerPoint[0] + deltaLat * (i + 1)
        const bottomLat = (-nPoints * deltaLat / 2) + centerPoint[0] + deltaLat * i 
        for (let j = 0; j < nPoints; j++) {
            const leftLng = (-nPoints * deltaLng / 2) + centerPoint[1] + deltaLng * j 
            const rightLng = (-nPoints * deltaLng / 2) + centerPoint[1] + deltaLng * (j + 1)

            grid.push(L.rectangle([[topLat, leftLng], [bottomLat, rightLng]], {
                color: '#ccc',
                weight: 1,
            }))
        }
    }

    return grid
}

export { calculateSquareIndex, calculateSquareMatrixPosition, createGridPositions, createLeafletGridSquares }