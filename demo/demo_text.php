<?php

$demos = array(
    array(
        'old' => '<p>Return air openings for heating, ventilation and air-conditioning systems shall comply with all of the following:</p>
<ol>
<li>Openings shall not be located less than 10 feet (3048 mm) measured in any direction from an open combustion chamber or draft hood of another appliance located in the same room or space.</li>
<li>Return air shall not be taken from a hazardous or insanitary location or a refrigeration room as defined in this code.</li>
<li>The amount of return air taken from any room or space shall be not greater than the flow rate of supply air delivered to such room or space.</li>
<li>Return and transfer openings shall be sized in accordance with the appliance or equipment manufacturer\'s installation instructions, ACCA Manual D or the design of the registered design professional.</li>
<ol><li>This is me previously.</li></ol>
<li>Return air taken from one dwelling unit shall not be discharged into another dwelling unit.</li>
<li>Taking return air from a crawl space shall not be accomplished through a direct connection to the return side of a forced air furnace. Transfer openings in the crawl space enclosure shall not be prohibited.</li>
<li>Return air shall not be taken from a closet, bathroom, toilet room, kitchen, garage, boiler room, furnace room or unconditioned attic.</li>
</ol>
<ul class="exception">
<li><strong>Exceptions:</strong>
<ol>
<li>Taking return air from a kitchen is not prohibited where such return air openings serve the kitchen and are located not less than 10 feet (3048 mm) from the cooking appliances.</li>
<li>Dedicated forced air systems serving only the garage shall not be prohibited from obtaining return air from the garage.</li>
</ol></li>
</ul>',

        'new' => '<p>Return air openings for heating, ventilation and air-conditioning systems shall comply with all of the following:</p>
<ol>
<li>Openings shall not be located less than 10 feet (3048 mm) measured in any direction from an open combustion chamber or draft hood of another appliance located in the same room or space.</li>
<li>Return air shall not be taken from a hazardous or insanitary location or a refrigeration room as defined in this code.</li>
<ol><li>This is me after the fact.</li></ol>
<li>The amount of return air taken from any room or space shall be not greater than the flow rate of supply air delivered to such room or space.</li>
<li>Return and transfer openings shall be sized in accordance with the appliance or equipment manufacturer\'s installation instructions, ACCA Manual D or the design of the registered design professional.</li>
<li>Return air taken from one dwelling unit shall not be discharged into another dwelling unit.</li>
<li>Taking return air from a crawl space shall not be accomplished through a direct connection to the return side of a forced air furnace. Transfer openings in the crawl space enclosure shall not be prohibited.</li>
<li>Return air shall not be taken from a closet, bathroom, toilet room, kitchen, garage, boiler room, furnace room or unconditioned attic.</li>
</ol>
<ul class="exception">
<li><strong>Exceptions:</strong>
<ol>
<li>Taking return air from a kitchen is not prohibited where such return air openings serve the kitchen and are located not less than 10 feet (3048 mm) from the cooking appliances.</li>
<li>Dedicated forced air systems serving only the garage shall not be prohibited from obtaining return air from the garage.</li>
</ol></li>
</ul>'
    )
);
header('Content-Type: application/json');
echo json_encode($demos);
