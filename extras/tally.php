<?php
/*
    tally.php - an RPC-based "rich list" generator

    (c) 2014 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    Licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
*/

if( !isset($argv[1]) )
    die( "usage: php {$argv[0]} <output filename>\n" );

$numAddresses = 100;
$txBufferSize = 150;

$rpcUser = 'birdonwheels5';
$rpcPass = 'pass';
$rpcHost = '127.0.0.1';
$rpcPort = 10889;

require_once( 'easybitcoin.php' );
$rpc = new Bitcoin( $rpcUser, $rpcPass, $rpcHost, $rpcPort );

$i = $txTotal = 0;
$next = $rpc->getblockhash( 1 );
$numBlocks = $rpc->getinfo()['blocks'];
echo "$numBlocks blocks ... ";
while( ++$i <= $numBlocks )
{
    if( $i % 1000 == 0 )
        echo "$i (tx# $txTotal)   ";

    $block = $rpc->getblock( $next );
    foreach( $block['tx'] as $txid )
    {
        $txTotal++;
        $tx = $rpc->getrawtransaction( $txid, 1 );

        foreach( $tx['vout'] as $vout )
            if( $vout['value'] > 0.0 )
                @$addresses[$vout['scriptPubKey']['addresses'][0]] += $vout['value'];

        foreach( $tx['vin'] as $vin )
            if( ($refOut = @$vin['txid']) )
            {
                if( array_key_exists($refOut, $txBuffer) )
                    $refTx = &$txBuffer[$refOut];
                else
                    $refTx = $rpc->getrawtransaction( $refOut, 1 );
                $addresses[$refTx['vout'][$vin['vout']]['scriptPubKey']['addresses'][0]] -= $refTx['vout'][$vin['vout']]['value'];
                unset( $refTx );
            }

        $txBuffer[$txid] = $tx;
        if( count($txBuffer) > $txBufferSize )
            array_shift( $txBuffer );
    }
    $next = $block['nextblockhash'];    
}
$rpc = null;

natsort( $addresses );
$addresses = array_reverse( $addresses );

file_put_contents( $argv[1],
	"<html>
	<head>
		<meta charset=\"ISO-8859-1\">
		<title>MYR Rich List</title>
		<link rel=\"stylesheet\" type=\"text/css\" href=\"https://birdonwheels5.no-ip.org/styles.css\" title=\"Default Styles\" media=\"screen\"/>
		<link rel=\"stylesheet\" type=\"text/css\" href=\"http://fonts.googleapis.com/css?family=Open+Sans\" title=\"Font Styles\"/>
	</head>
	
	<body link=\"#E2E2E2\" vlink=\"#ADABAB\" style=\"background-image:url(https://birdonwheels5.no-ip.org/img/stars3.jpg);\">
		<header>
		
				<div class=\"logoContainer\">
					<img src=\"https://birdonwheel5.no-ip.org/img/logo.png\" alt=\"Myriadcoin Logo\" style=\"width:130%;\">
				</div>

				<div class=\"button\">
					<p><a href =\"http://birdonwheels5.no-ip.org:3000\">Back to Block Explorer</a></p>
				</div>
				
			</header>
		<article style=\"color:#FFFFFF;\">
				<p>
					<center><img src=\"https://birdonwheel5.no-ip.org/logo.gif\"></center>
					
					<hr/>
					<center><h1>Myriadcoin Rich List (Compiled daily @ 1 AM EST)</h1></center>
					<hr/>
					<p>
						<h2><u>Top 100 Richest Myriadcoin Addresses</u></h2><br/<br/>
	
						<p> " );

$i = 0;
while( (list($key, $value) = each( $addresses )) && $i++ < $numAddresses )
    file_put_contents( $argv[1], "$key $value" . " MYR\n" );
	
file_put_contents( $argv[1],
	"</p>
	<br/><br/>
		</article>
			
			<div class=\"paddingBottom\">
			</div>
			
			<footer>
				2014 birdonwheels5.
			</footer>
		</div>
	</body>
	
</html>" );

echo "done! $txTotal transactions through " . count( $addresses ) . " unique addresses counted\n";

?>
