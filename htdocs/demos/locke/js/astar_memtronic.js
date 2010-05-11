// (C) Andrea Giammarchi
//	Special thanks to Alessandro Crugnola [www.sephiroth.it]
function AStar(Grid, Start, Goal, Find) {
    function AStar() {
        switch (Find) {
        case "Diagonal":
        case "Euclidean":
            Find = DiagonalSuccessors;
            break;
        case "DiagonalFree":
        case "EuclideanFree":
            Find = DiagonalSuccessors$;
            break;
        default:
            Find = function () {};
            break;
        };
    };

    function $Grid(x, y) {
        return Grid[y][x] === 0;
    };

    function Node(Parent, Point) {
        return {
            Parent: Parent,
            value: Point.x + (Point.y * cols),
            x: Point.x,
            y: Point.y,
            f: 0,
            g: 0
        };
    };

    function Path() {
        var $Start = Node(null, {
            x: Start[0],
            y: Start[1]
        }),
            $Goal = Node(null, {
                x: Goal[0],
                y: Goal[1]
            }),
            AStar = new Array(limit),
            Open = [$Start],
            Closed = [],
            result = [],
            $Successors, $Node, $Path, length, max, min, i, j;
        while (length = Open.length) {
                max = limit;
                min = -1;
                for (i = 0; i < length; i++) {
                    if (Open[i].f < max) {
                        max = Open[i].f;
                        min = i;
                    }
                };
                $Node = Open.splice(min, 1)[0];
                if ($Node.value === $Goal.value) {
                    $Path = Closed[Closed.push($Node) - 1];
                    do {
                        result.push([$Path.x, $Path.y]);
                    } while ($Path = $Path.Parent);
                    AStar = Closed = Open = [];
                    result.reverse();
                } else {
                    $Successors = Successors($Node.x, $Node.y);
                    for (i = 0, j = $Successors.length; i < j; i++) {
                        $Path = Node($Node, $Successors[i]);
                        if (!AStar[$Path.value]) {
                            $Path.g = $Node.g + Distance($Successors[i], $Node);
                            $Path.f = $Path.g + Distance($Successors[i], $Goal);
                            Open.push($Path);
                            AStar[$Path.value] = true;
                        };
                    };
                    Closed.push($Node);
                };
            };
        return result;
    };

    function Successors(x, y) {
        var N = y - 1,
            S = y + 1,
            E = x + 1,
            W = x - 1,
            $N = N > -1 && $Grid(x, N),
            $S = S < rows && $Grid(x, S),
            $E = E < cols && $Grid(E, y),
            $W = W > -1 && $Grid(W, y),
            result = [];
        if ($N) result.push({
                x: x,
                y: N
            });
        if ($E) result.push({
                x: E,
                y: y
            });
        if ($S) result.push({
                x: x,
                y: S
            });
        if ($W) result.push({
                x: W,
                y: y
            });
        Find($N, $S, $E, $W, N, S, E, W, result);
        return result;
    };

    function DiagonalSuccessors($N, $S, $E, $W, N, S, E, W, result) {
        if ($N) {
            if ($E && $Grid(E, N)) result.push({
                x: E,
                y: N
            });
            if ($W && $Grid(W, N)) result.push({
                x: W,
                y: N
            });
        };
        if ($S) {
            if ($E && $Grid(E, S)) result.push({
                x: E,
                y: S
            });
            if ($W && $Grid(W, S)) result.push({
                x: W,
                y: S
            });
        };
    };

    function DiagonalSuccessors$($N, $S, $E, $W, N, S, E, W, result) {
        $N = N > -1;
        $S = S < rows;
        $E = E < cols;
        $W = W > -1;
        if ($E) {
            if ($N && $Grid(E, N)) result.push({
                x: E,
                y: N
            });
            if ($S && $Grid(E, S)) result.push({
                x: E,
                y: S
            });
        };
        if ($W) {
            if ($N && $Grid(W, N)) result.push({
                x: W,
                y: N
            });
            if ($S && $Grid(W, S)) result.push({
                x: W,
                y: S
            });
        };
    };

    function Diagonal(Point, Goal) {
        return max(abs(Point.x - Goal.x), abs(Point.y - Goal.y));
    };

    function Euclidean(Point, Goal) {
        return sqrt(pow(Point.x - Goal.x, 2) + pow(Point.y - Goal.y, 2));
    };

    function Manhattan(Point, Goal) {
        return abs(Point.x - Goal.x) + abs(Point.y - Goal.y);
    };
    var abs = Math.abs,
        max = Math.max,
        pow = Math.pow,
        sqrt = Math.sqrt,
        cols = Grid[0].length,
        rows = Grid.length,
        limit = cols * rows,
        Distance = {
            Diagonal: Diagonal,
            DiagonalFree: Diagonal,
            Euclidean: Euclidean,
            EuclideanFree: Euclidean,
            Manhattan: Manhattan
        }[Find] || Manhattan;
    return Path(AStar());
};
