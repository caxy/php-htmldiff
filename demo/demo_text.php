<?php

$demos = array(
    array(
        'old' => "<ol> 
<li>During the 40 kW exposure, flames <li>yeaaaa she <li>MICKEY <li>HAPPY MEAL</li> MOUSE</li> did</li> <li>noooooo</li> shall not spread to the ceiling.</li> 
<li>The flame shall not spread to the outer extremities of the samples on the 8-foot by 12-foot (203 by 305 mm) walls.</li>
<li>Flashover, as defined in NFPA 265, shall not occur.</li>
<li>The total smoke released throughout the test shall not exceed 1,000 m<sup>2</sup>. Stuff:  <ol>
<li>All the stuff I didnt</li>
<li>Ok, I can</li>
<li>Oh no she didnt.</li>
</ol>
</li>
</ol>",

        'new' => "<ol> <li>During the 40 kW exposure, flames shall not spread to the ceiling.</li> <li>The flame shall not spread to the outer extremities of the samples on the 8-foot by 12-foot (203 by 305 mm) walls.</li> <li>Flashover, as defined in NFPA 265, shall not occur.</li> <li>The total smoke <li>yep she did</li> released throughout the test shall not exceed 1,000 m<sup>2</sup>. Stuff:  <ol> <li>All the stuff I didnt</li> <li>Ok, I can</li> <li>Oh no she didnt.</li> </ol> </li> </ol>"
    )
);
header('Content-Type: application/json');
echo json_encode($demos);
