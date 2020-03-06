<?php
//SOAP Client



$wsdl = "https://www.wcc.nrcs.usda.gov/awdbWebService/services?WSDL";
$client = new SoapClient($wsdl, array(
        'trace' => true, //to debug
    ));
$series = array();

$params = array('SNWD','TAVG','TMIN','TMAX','WTEQ');


try {
    $args = array(
        'minLatitude'=> '68',
        'minLongitude'=> '-155',
        'maxLongitude'=> '-140',
        'logicalAnd' => 'true',
        'elementCd'  => 'TAVG,SNWD',
         //'networkCds' => 'SNTL'
        );

    $result = $client->__soapCall('getStations', array($args));

} catch (SoapFault $e) {
    echo "Error: {$e}";
}


//to debug the xml sent to the service
//echo($client->__getLastRequest());
//to view the xml sent back
//echo($client->__getLastResponse());
//print_r($result->return);
$sites = $result->return;
//echo $sites;




try {
    $args = array(
        'stationTriplets'=> $sites
        );

    $result = $client->__soapCall('getStationMetadataMultiple', array($args));

} catch (SoapFault $e) {
    echo "Error: {$e}";
}

$siteMeta = $result->return;

$site = (empty($_GET['site'])) ? $siteMeta[0]->stationTriplet : $_GET['site'];
$code = (empty($_GET['code'])) ? 'SNWD' : $_GET['code'];
$siteObj;


try {
    $args = array(
        'stationTriplets'=> $site,
        'elementCd'=> $code,
        'ordinal'=> '1',
        'duration'=> 'DAILY',
        'getFlags'=> 'false',
        'beginDate'=>'1900-1-1',
        'endDate'=> '2020-1-1',
        'alwaysReturnDailyFeb29' => false
    );

    $result = $client->__soapCall('getData', array($args));

} catch (SoapFault $e) {
    echo "Error: {$e}";
}



try {
    $args = array(
        'stationTriplets'=> $site,
        'elementCd'=> $code,
        'ordinal'=> '1',
        'beginDate'=> date('Y-m-d',strtotime("-7 day")),
        'endDate'=> date('Y-m-d',strtotime("+7 day")),
//        'heightDepth'=> array(
//              'unitCd' => 'in',
//              'value' =>-2              ),
    );

    $hourlyResult = $client->__soapCall('getHourlyData', array($args));

} catch (SoapFault $e) {
    echo "Error: {$e}";
}

$allSeries = array();


$start = strtotime($result->return->beginDate)+12*3600;
$series['name'] = "Spring: ".date('Y',$start+365*24*3600);
$opacity = 1-(2019-intval(date('Y',$start+365*24*3600)))/10;
$series['data'] = array();
$series['marker']['enabled'] = false;
$series['color'] = 'rgba(51, 171, 240,'.$opacity.')';
$series['lineWidth'] = 2;

$base = $start;
foreach($result->return->values as $val){
    if(date('m',$start)==8 && date('d',$start)==1) {
        $allSeries[] = $series;
        $series['name'] = 'Spring: '.date('Y',$start+365*24*3600);
        $series['data'] = array();
        $opacity = 1-(2019-intval(date('Y',$start+365*24*3600)))/10;
        echo $opacity;
        $series['color'] = 'rgba('.($opacity*100).', 171, 240,'.$opacity.')';

        $base = $start;
        if(date('Y',$start+365*24*3600) == '2018'){
            $series['color'] = 'red';
            $series['lineWidth'] = 2;
         }
        if(date('Y',$start+365*24*3600) == '2017'){
            $series['color'] = 'black';
            $series['lineWidth'] = 2;
         }
        if(date('Y',$start+365*24*3600) == '2016'){
            $series['color'] = 'brown';
            $series['lineWidth'] = 2;
         }
        if(date('Y',$start+365*24*3600) == '2015'){
            $series['color'] = 'tan';
            $series['lineWidth'] = 2;
         }
        if(date('Y',$start+365*24*3600) == '2014'){
            $series['color'] = '#FAD7A0';
            $series['lineWidth'] = 2;
         }
    }
    $sameYear = strtotime('2016-8-1')+($start-$base);
    $series['data'][] = array($sameYear*1000,intval($val));
    $start = $start+3600*24;
}


$allSeries[] = $series;

$start = strtotime($hourlyResult->return->beginDate)+12*3600;
$series['name'] = 'Latest Hourly';
$series['data'] = array();
$base = $start;
foreach($hourlyResult->return->values as $val){
    $sameYear = strtotime('2016-8-1')+($start-$base);
    $date = strtotime($val->dateTime);
    $series['data'][] = array($date*1000,intval($val->value));

}

$allSeries[] = $series;



?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>


<form action="<?=$_SERVER['PHP_SELF'];?>" id="nrcsform">
<select name="code">
<?
   foreach($params as $p){
        $option = '';
        if($p == $code){
            $option = 'selected';
        }
        echo '<option '.$option.' value="'.$p.'">'.$p.'</option>';
    }
?>
</select>
<select name="site">
<?
    foreach($siteMeta as $meta){
        $option = '';
        if($meta->stationTriplet == $site){
            $siteObj = $meta;
            $option = 'selected';
        }
        echo '<option '.$option.' value="'.$meta->stationTriplet.'">'.$meta->name.'</option>';
    }
?>
</select>
<input type="submit" value="Plot Data"><br>
</form>

<div id="container" style="min-width: 310px; height: 400px; margin: 0 auto"></div>
<button id="button">Show all series</button>

<script>
$(function () {
    $('#container').highcharts({
        chart: {
            type: 'line',
            zoomType: 'xy'
        },
        title: {
            text: 'Snow depth <?echo $siteObj->name;?>'
        },
        xAxis: {
            type: 'datetime',
            dateTimeLabelFormats: { // don't display the dummy year
                month: '%e. %b',
                year: '%b'
            },
            title: {
                text: 'Date'
            }
        },
        yAxis: {
            title: {
                text: 'Snow depth (cm)'
            }
        },
        tooltip: {
            headerFormat: '<b>{series.name}</b><br>',
            pointFormat: '{point.x: %b %e %Y}: {point.y:.2f} in'
        },

        plotOptions: {
            spline: {
                marker: {
                    enabled: false
                }
            }
        },

        series: <? echo json_encode($allSeries);?>
    });

    var chart = $('#container').highcharts(),
        $button = $('#button');
    $button.click(function() {
        if ($button.html() == 'Hide all series') {
            $(chart.series).each(function(){
                this.setVisible(false, false);
            });
            chart.redraw();
            $button.html('Show all series');
        } else {
            $(chart.series).each(function(){

                this.setVisible(true, false);
            });
            chart.redraw();
            $button.html('Hide all series');
        }
    });
    $(chart.series).each(function(){

        this.setVisible(false, false);
    });
    chart.redraw();
});
</script>