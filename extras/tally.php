<?php
/*
    tally.php - an RPC-based "rich list" generator

    (c) 2014 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    Licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
*/

if( !isset($argv[1]) )
    die( "usage: php {$argv[0]} <output filename>\n" );

$numAddresses = 1000;
$txBufferSize = 150;

$rpcUser = 'birdonwheels5';
$rpcPass = 'pass';
$rpcHost = '127.0.0.1';
$rpcPort = 10889;

$abort = false;
$numAddresses = 1000;
$txBufferSize = 150;
$resume = "$rpcUser-$rpcPort-tally.dat";

if( !isset($argv[1]) )
    die( "Usage: php {$argv[0]} <output filename>\n" );

function handleInt()
{
    global $abort;
    $abort = true;
}
pcntl_signal( SIGINT, 'handleInt' );

require_once( 'easybitcoin.php' );
$rpc = new Bitcoin( $rpcUser, $rpcPass, $rpcHost, $rpcPort );
$numBlocks = $rpc->getinfo()['blocks'];
if( $rpc->status !== 200 && $rpc->error !== '' )
    die( "Failed to connect. Check your coin's .conf file and your RPC parameters.\n" );

$i = $txTotal = 0;
if( file_exists($resume) )
{
    $tally = unserialize( file_get_contents($resume) );
    if( $tally['tally'] === true )
    {
        $i = $tally['last'];
        $txTotal = $tally['txTotal'];
        $addresses = $tally['addresses'];
        $numAddresses = $tally['numAddresses'];
        echo 'resuming from block ' . ( $i + 1 ) . ' - ';
    }
}

$next = $rpc->getblockhash( $i + 1 );
echo "$numBlocks blocks ... ";
while( ++$i <= $numBlocks && $abort === false )
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
    if( ($next = @$block['nextblockhash']) === null )
        $abort = true;

    pcntl_signal_dispatch();
}
$rpc = null;

// save progress
file_put_contents( $resume, serialize(['tally'=>true,
                                       'numAddresses'=>$numAddresses,
                                       'addresses'=>$addresses,
                                       'txTotal'=>$txTotal,
                                       'last'=>$i-1]) );

natsort( $addresses );
$addresses = array_reverse( $addresses );

file_put_contents( $argv[1],
	"<html>
	<head>
		<meta charset=\"ISO-8859-1\">
		<title>MYR Rich List</title>
		<link rel=\"stylesheet\" type=\"text/css\" href=\"http://birdspool.no-ip.org:5567/static/styles.css\" title=\"Default Styles\" media=\"screen\"/>
		<link rel=\"stylesheet\" type=\"text/css\" href=\"http://fonts.googleapis.com/css?family=Open+Sans\" title=\"Font Styles\"/>
	</head>
	
	<body link=\"#E2E2E2\" vlink=\"#ADABAB\" style=\"background-image:url(http://birdspool.no-ip.org:5567/static/img/stars3.jpg);\">
	
	<center><div class=\"container\">
	
		<header>
		
				<div class=\"logoContainer\">
					<img src=\"http://birdspool.no-ip.org:5567/static/img/logo.png\" alt=\"Myriadcoin Logo\" style=\"width:130%;\">
				</div>

				<div class=\"button\">
					<p><a href =\"http://birdonwheels5.no-ip.org:3000\">Back to Block Explorer</a></p>
				</div>
				
			</header>
		<article style=\"color:#FFFFFF;\">
				<p>
					<center><img src=\"http://birdspool.no-ip.org:5567/static/img/logo.gif\" style=\"width:10%\";></center>
					
					<hr/>
					<center><h1>Myriadcoin Rich List (Compiled daily @ 1 AM EST)</h1></center>
					<hr/>
					<p>
						<h2><u>Top 1000 Richest Myriadcoin Addresses</u></h2><br/<br/>
	
						<table style=\"width:500px\";> " );

$i = 0;
while( (list($key, $value) = each( $addresses )) && $i++ < $numAddresses )
    file_put_contents( $argv[1], "<tr><td>$i) </td> <td><a href=\"http://birdonwheels5.no-ip.org:3000/address/$key\">$key </a></td> <td> " . number_format($value, 2, '.', ',') . " </td <td> MYR</td></tr>\n", FILE_APPEND );
	
file_put_contents( $argv[1],
	"</table>
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

echo ( $abort ? 'aborted -' : 'done!' ) . " $txTotal transactions through " . count( $addresses ) . " unique addresses counted\n";

?>
