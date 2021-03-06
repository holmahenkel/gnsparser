<?
// Routing protocols
// 1 = enabled

$feat_ripv2 = 1;
$feat_ripv6 = 1;
$feat_ospfv2 = 0;
$feat_ospfv3 = 0;
$feat_eigrpv4 = 0;
$feat_eigrpv6 = 0;
$feat_bgpv4 = 0;
$feat_bgpv6 = 0;

function rfeatures() {

	global $feat_ripv2, $feat_ripv6, $feat_ospfv2, $feat_ospfv3,
		$feat_eigrpv4, $feat_eigrpv6, $feat_bgpv4, $feat_bgpv6, $n;

	$rconfig = "";

if($feat_ripv2) {
$rconfig .= "router rip
 version 2
 no auto-summary
 network 10.0.0.0
!
";}

if($feat_ripv6) {
$rconfig .= "ipv6 router rip ripng
!
";}

if($feat_ospfv2) {
$rconfig .= "router ospf 100
 router-id 10.88.88.".$n["id"]."
!
";}

if($feat_ospfv3) {
$rconfig .= "ipv6 router ospf 100
 router-id 10.88.88.".$n["id"]."
!
";}

if($feat_eigrpv4) {
$rconfig .="router eigrp 100
 no auto-summary
 network 0.0.0.0 0.0.0.0
!
";}

if($feat_eigrpv6) {
$rconfig .="ipv6 router eigrp 100
 no shutdown
!
";}

if($feat_bgpv4) {
$rconfig .="";     
}

if($feat_bgpv6) {
$rconfig .="";
}

	return $rconfig;

}

// Interface specific routing features
function rfeatureif() {

	global $feat_ripv2, $feat_ripv6, $feat_ospfv2, $feat_ospfv3,
	        $feat_eigrpv4, $feat_eigrpv6, $feat_bgpv4, $feat_bgpv6;

	$rconfigif = "";

if($feat_ripv2) {
$rconfigif .= "";}

if($feat_ripv6) {
$rconfigif .= "
 ipv6 rip ripng enable";}

if($feat_ospfv2) {
$rconfigif .= "
 ip ospf 100 area 0";}

if($feat_ospfv3) {
$rconfigif .= "
 ipv6 ospf 100 area 0";}

if($feat_eigrpv4) {
$rconfigif .="";}

if($feat_eigrpv6) {
$rconfigif .="
 ipv6 eigrp 100";}

if($feat_bgpv4) {
$rconfigif .="";}

if($feat_bgpv6) {
$rconfigif .="";}

	return $rconfigif;
}

// Generate subnets based on node hostnames.
// If hostname is larger than 10 place it first.
// 103 instead of 301. 115 instead of 511.
function subnet($a,$b) {
	if($a>9 || $b>9) {
		if($a>9) {
			return $a.$b;
		} elseif($b>9) {
			return $b.$a;
		}
	} else {
		if($a<$b){
	                return $a.$b;
	        } else {
	                return $b.$a;
	        }
	}
}

//exec("mkdir ".$workDir."configs");

// Write content to file
function writefile($cont_hostname,$fcontent) {

		global $workDir;

		$fileout = $workDir."configs/".$cont_hostname.".cfg";

//		exec("mkdir ".$workDir."configs");
		exec("touch $fileout");

		if(is_writable($fileout)) {
			if(!$handle = fopen($fileout, "a+")) {
				echo "Error opening ".$fileout;
				exit;
			}
			if(fwrite($handle, $fcontent) === FALSE) {
				echo "Error writing ".$fileout;
			}
			fclose($handle);
		} else {
		echo "Unable to write file for host:".$cont_hostname." ".$fileout;
	}
}

// Clean old content
//exec("rm -rf out/*");

// Read the Dynamips .net file
// IN file is fetched from upload.php
//$in = file_get_contents("in/topology.net");
$router = explode("[[ROUTER ", $in);
unset($router[0]);

// Build the router device array parsing the .net file
$i = 0;
foreach($router as $r) {

	$lines = preg_split("/\\n/", $r);

	foreach($lines as $l) {
		$reg_hostname = preg_match_all("/^(.+)\\]\\]/", $r, $out_hostname);
		$hostname = array_shift($out_hostname[1]);

		$field = sscanf($l, "%s %s %s %s");
		if(!$field || !$field[2]) continue;

		$out[$i]["id"] = preg_replace("/\D/","",$hostname);

		// Only allow R1-R99 hostnames
		if(!preg_match("/^R\d+/",$hostname)) {
			$scriptError = "hostname";
			return("scriptError");
			exit;
		}
		
		// Set hostname in array
		$out[$i]["hostname"] = $hostname;
		$out[$i][$field[0]] = $field[2] ." ".$field[3];

        }
$i++;
}

//if($scriptError) {
//	return("scriptError");
//	exit;
//}

$curtime = date("d.m.y H:i:s T");
$curtime_conf = date("H:i:s d M Y");

// Build the config
$i = 0;
foreach($out as $n) {

	$keys = array_keys($n);
	$n_if = preg_grep("/[fs]\\d\\/\\d\.*/", $keys);

// Initial configuration
$config = "!
! Generated by GNSparser
! http://gns.holmahenkel.com
! ".$curtime."
!
hostname ".$n["hostname"]."
!
!clock set ".$curtime_conf."
!
ip cef
ipv6 unicast-routing
ipv6 cef
!
service timestamps debug datetime msec
service timestamps log datetime msec
no service password-encryption
no service config
no ip domain lookup
no ip icmp rate-limit unreachable
ip tcp synwait 5
!
enable password 0 cisco
username cisco password 0 cisco
!
logging console 7
logging buffered 7
!
line con 0
 exec-timeout 0 0
 logging synchronous
 privilege level 15
 no login
line aux 0
 exec-timeout 0 0
 logging synchronous
 privilege level 15
 no login
line vty 0 15
 exec-timeout 0 0
 privilege level 15
 login local
 transport input telnet
! 
";

writefile($n["hostname"],$config);

// Enabling routing protocols
writefile($n["hostname"],rfeatures());

// Configuration of loopback interfaces
$ifloopback = "interface lo0
 description R".$n["id"]."
 ip address 10.0.0.".$n["id"]." 255.255.255.255
 ipv6 address 2001:DB8::".$n["id"]."/128";
$ifloopback .= rfeatureif();
$ifloopback .= "
!
interface lo101
 description LAN1
 ip address 10.0.".$n["id"]."1.1 255.255.255.0
 ipv6 address 2001:DB8:0:".$n["id"]."0::1/64";
$ifloopback .= rfeatureif();
$ifloopback .= "
!
interface lo102
 description LAN2
 ip address 10.0.".$n["id"]."2.1 255.255.255.0
 ipv6 address 2001:DB8:0:".$n["id"]."1::1/64";
$ifloopback .= rfeatureif();
$ifloopback .= "
!
interface lo103
 description LAN3
 ip address 10.0.".$n["id"]."3.1 255.255.255.0
 ipv6 address 2001:DB8:0:".$n["id"]."2::1/64";
$ifloopback .= rfeatureif();
$ifloopback .= "
!
";

// Write loopback interfaces to file
writefile($n["hostname"],$ifloopback);

foreach($n_if as $if) {

$n_edge = preg_match_all("/^R(\\d+)/m", $n[$if], $e);
$a = $n["id"];
$b = $e["1"]["0"];

$ifethernet = "interface ".$if."
 no switchport
 description ".$n[$if]."
 ip address 10.99.". subnet($a,$b) .".".$n["id"]." 255.255.255.0
 ipv6 address 2001:DB8:99:". subnet($a,$b) ."::".$n["id"]."/64
 speed 100
 duplex full";
$ifethernet .= rfeatureif();
$ifethernet .= "
 no shut
!
";

// Write Ethernet interfaces to file
writefile($n["hostname"],$ifethernet);
   
}
$i++;
}

//$generated = TRUE;

//exec("sed -i \"/end/d\" out/*");
//exec("zip -r out/archive.zip out/*.cfg");
#exec("rm -rf out/*");

//die();

?>
