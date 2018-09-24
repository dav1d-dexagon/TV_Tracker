<?php
##      DEX_TV_Tracker.php
##      Dav1d Grossman, Dexagon Inc.
##      First release: 2015-12-31
##
##  This is a very simple program that keeps track of TV shows that are being followed, by tracking
##  the last episode that has been watched, and alerting when new episodes are available.
##  It is a good tool to use for "cable-cutters" who don't want to miss any episodes of their 
##  favourite shows.  Note: Some of the programs that are used to watch TV from the Internet, such
##  as "Kodi/XBMC" already track watched episodes, and have plugins that will alert when new
##  episodes are available. If that program, and only that program, is the only one being used for
##  watching TV, it is a good idea to use the built-in episode tracker(s).  However, if you
##  are using different devices and/or different software to watch the TV episodes, this 
##  stand-alone program might be a better choice, since it is available on the web at all times,
##  and is simple enough to use from any TV that can browse the web, and is not tied to any
##  particular TV viewing program.
##
##
##  DEX_TV_Tracker is a very simple program, that does this:
##    0. Initialize
##    1. getShows()
##    2. if (update | add | delete) 
##     2.1 updateShows()
##     2.2 putShows()
##    3. showShows()
##
##  Calling this program:
##    no parameters             Simply display the info table on the screen
##    ?TVMID=999&EPNUM=S99x99   Update show TVMID to watched episode S99x99 and then display table
##    ?TVMID=999&ADD            Add show TVMID  (manually find the correct TVMID using "Search by Name")
##    ?TVMID=999&DEL            Delete show TVMID    (find the correct TVMID using "Search by Name")
##
##
##  The file to be read has the SAME NAME as this program, but it ends in ".dex" instead of ".php"
##  The format of the data file is as follows:
##      Line begins with            means
##          #                       This is a comment, and can contain ANYTHING
##          !                       This is a configuration directive (see configuration, below)
##          letter/digit            This line contains data about a TV show (See details, below)
##
##  The data file *CAN* be manually modified, but be careful to maintain the integrity of the file
##  so it can still be read and written by this program. This is important!
##
##  Internally (ie. within the program), all of the data that is read from the data file, as well as
##  all of the data retrieved from online TV databases is stored in a single php array, for ease of
##  access.  The data is collected at the start of the program, and written back to the file only
##  when a specific request is made (by the user) to update certain things (directives or episodes).
##
##  The internal GLOBAL array is called $SHOWS. It is a 2D array with one line for each TV show 
##  listed in the data portion of the data file.  The first three fields are the data from the file
##  and the fourth field is a JSON string retrieved from TVMaze.com, which contains all of the show
##  data. The fifth field is UNSEEN. The sixth field is UNAIRED. The seventh field is the count of
##  total episodes for this show. The eighth field is the episode number for the NEXT episode, if 
##  any. The ninth field is an array pointer into the JSON for that show, which can be used to 
##  to get any information about that show, including details on the last or next episode(s).
##
##
##  Example:
##   ShowID         $SHOWS[$i][0] = "1850"          |   [FILE] TVMaze.com database ID for this show
##   Ep_Number      $SHOWS[$i][1] = "S01E08"        |   [FILE] Episode Number of last watched show
##   Ep_Watched     $SHOWS[$i][2] = "2015-12-21"    |   [FILE] Date that last episode was watched
##
##   Sh_JSONtxt     $SHOWS[$i][3] = "Supergirl ..." |   Entire JSON (as text string) from TVMaze.com
##   Sh_TotalEps    $SHOWS[$i][4] = "999"           |   Total number of episodes for this show
##   Sh_Unseen      $SHOWS[$i][5] = "3"             |   Number of unseen episodes that have aired
##   Sh_Unaired     $SHOWS[$i][6] = "2"             |   Number of unaired episodes
##
##   Ep_Next        $SHOWS[$i][7] = "S01E09"        |   Episode number for NEXT episodes. Use this 
##                                                  |   to pass back to this program for updates. 
##                                                  |   Do not pass any internal array pointers, as
##                                                  |   they could change if the user edits the file
##                                                  |   between sessions of running this program!
##
##   Ep_Watched_ptr $SHOWS[$i][8] = "9"             |   Pointer into "episode array" within the JSON
##                                                  |   which can be used to get the details about
##                                                  |   the WATCHED episode (name, summary) as well
##                                                  |   as the details for the NEXT episode.  Since
##                                                  |   this is a pointer into the ESPISODE array
##                                                  |   within the JSON for this show, the NEXT
##                                                  |   episode is the one immediately after this.
##
##   IMPORTANT! The first row of the global $SHOWS array contains titles, so make sure you always
##   start the pointer into this array at number 1 (instead of the typical 0).  This should really 
##   be fixed in the entire program, but will need to be changed all at ance. Plan a 2-hour session.
##
##   The size of the global $SHOWS array should always be determine using:
##              $count = count($SHOWS) -1 ;
##   because this array size information is not contained in a global variable, and also because
##   it changes often.  Get it whenever you need it!
##
##
##  The internal array that contains the directives is called "$DIRECT", is a really a hash with
##  keywords that represent the configurable parameters that are read from and written to the file.
##
##  The file really contains only THREE critical pieces of information:  The TV show ID (which is 
##  a pointer into the TVMaze.com database), the last watched episode, and the date that the last
##  episode was watched.  All of ther information comes from the TVMaze.com database.  (There is a
##  fourth field in the data file, which is the name of the show, but it is only really there for
##  "human-readable" reasons. It is *NOT* used in the table display.
##
##  Configuration Directives, which can appear ANYWHERE in the data file:
##    !SORT= { name | unseen | watched | aired | ... }
##    
##  Each episode line must contain at least three fields separated by commas
##     ID       a numeric pointer into the TVMaze.com database. This pointer (or ID) is used to 
##              retrieve information about the TV show, including all episodes of that show.
##     Episode  a string of the format SssEee where "S" is a capital S, "ss" is the season, 
##              "E" is a capital "E", and "ee" is the episodes within that season. eg. S03E06
##     Watched  an ISO8601 date string "yyyy-mm-dd"  (hours, minutes, seconds, and timeszones
##              are not stored nor tracked in any way)
##     Name     {optional} a double-quote enclosed string which is the name of the show from the
##              TVMaze.com database. This name is not used, and is overwritten with TVMaze.com info.
##
##  Examples:
##     1850,S01x03,2015-12-09,"Supergirl"
##     1,S01x01,2015-12-21,"Under the Dome"
##
##
##  Other information:
##     This is not a multi-user program, so no locking has been implemented. It reads from a local
##     text file, so the user shouldn't manually edit the text file *WHILE* this program is running.
##
##  Writing back the data file is a bit tricky.  Originally, the goal was to simply write all of the
##  show data, and therefore this would be a standard CSV file.  However, all comments in the file
##  would be lost, and that's not a good thing. The second thought was to simply store all of the 
##  comments as they were being read at initialization time, and then write them all back out to
##  to the data file in order, and then write the data for the shows.  However, that means that all
##  comments will be at the beginning of the file, but if they referred to any specific lines in
##  the data, that juxtapositioning would be lost.  So, the real answer is to read the data file
##  line by line, and output the line UNTOUCHED if it is not a "data" line, or put fresh data if
##  if *IS* a data line.  That will maintain the order of lines in the file, including their
##  positions, but the lines that actually contain data will be updated with the new information
##  from the global array.  Obviously, this means that the new file will be written with a 
##  temporary name, and then, after it is written, the old one will be deleted and the new one
##  will be properly renamed.  This is safer anyway, as there are (almost) always TWO copies of 
##  the data file.  In fact, the old one can be renamed instead of deleted, to maintain a backup
##  copy.  These smaller details are not finalized yet.
##
##  To add a new show, you will have to find the ID in the TVMaze.com database for that show. This
##  can be done using the "Search by Name" function provided at the top of the page.  Look in the
##  provided URL for the show name, and replace it with the name of the show that you want to add 
##  to your watch list.  Then, add a line to your data file that begins with this showID, and the
##  program will include that show in the new table refresh.
##
##  To make the task easier for debugging, use the JSON formatter (link also provided at the top
##  of the page) to see that contents of the "Search by Name" in an easier to read format.
##
##  2016-03-03: New concept: Should the data file be a JSON file, instead of an ugly mix of
##              comments and individual lines of CSV text?  A JSON file will be easier to maintain,
##              easier to be read by humans, easier to update, easier to be enhanced with other 
##              data, and easier to be modified by other programs, or other programmers. HHhhmm...
##

##
##  TODO:
##      * Locations marked with #!# still need completion  (Delete a SHOW in array, order of getShows, ...)
##      * fix the "next Episode ID" when an episode update is performed
##      * BUG: when *NO* episodes have been seen, the SECOND episode details are shown, instead of the first
##      * fix episode ID's to always be SxxxExxx, epsecially for shows that use years instead of seasons!
##      * fix DELETE SHOW in the navbar
##      * Write help file on the same front page
##      * Fix old copies to avoid clutter: only a few, or perhaps only with current date?
##      * Make search easier by autofilling the name from the user, instead of modifying the "Castle" URL
##      * Make search easier by using an AJAX dialog to find and present the user for approval 
##      * Allow a user comment/note for each program (ie. series) 

#  Turn on all error reporting
    error_reporting( E_ALL );
    ini_set( "display_errors", 1 );

?><!DOCTYPE HTML>
<html>
<head>
<TITLE>My TV Shows</TITLE>
<LINK REL="icon" type="image/png" target='_blank' href="http://dexware.com/favicon.ico" >
<link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>

<STYLE TYPE="text/css">
<!--
body,table,tr,td,p,a
            {font-family:'Droid Sans',sans-serif;font-size:12px;line-height:14px; color:#0000CC}
A:link      {color:#CC0000; font-weight:bold; text-decoration:none; }
A:visited   {color:#CC0000; font-weight:bold; text-decoration:none; }
A:active    {color:#CC0000; font-weight:bold; text-decoration:none; }
A:focus     {color:#000099; font-weight:bold; text-decoration:underline; }
A:hover     {color:#000099; font-weight:bold; text-decoration:underline; }

.tdchop     {float:left;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.newEps     {background-color:yellow;color:navy;font-weight:bold;text-align:center;}
.no_Eps     {font-weight:bold;text-align:center;}
.unkEps     {font-weight:bold;text-align:center;}
.sepEps     { background-color:navy;}        
.rowA       { background-color:#FFFFFF; }
.rowB       { background-color:#EEEEEE; }
.reverse    { background-color:navy;color:white;}
.message    { background-color:yellow;color:navy;border:1px black solid;}

-->
</STYLE>

<!-- http://www.kryogenix.org/code/browser/sorttable/ -->
<script src='http://www.kryogenix.org/code/browser/sorttable/sorttable.js'></script>



<?php

    # get the filename so we can determine the name of the data file
    $NAME =  basename($_SERVER["SCRIPT_NAME"]) ;
    $VERS = "v0.06";
    $FILE = substr($NAME ,0,strlen($NAME)-4) . ".dex" ;
    
    
?>













</head>



    <script>
//        $( document ).ready(function() {
//            spinnerOn();
//        });
// 
//        $( window ).load(function() {
//            spinnerOff();
//        });
    </script>





<body>





<!-- First Real output goes here -->


<table cellpadding='0' cellspacing='0' border='1' width='999px'>
  <tr>
    <td title='REFRESH'>
        <a href=javascript:spinnerOn();window.location.href=window.location.href.split('?')[0]; ><img Xwidth='32' height='32' src='http://dexagon.com/graphics/dex-logo-both-clear.gif' align='center'></a>
    </td>
    <td title='REFRESH' class='reverse' align='center'>
        <a href=javascript:spinnerOn();window.location.href=window.location.href.split('?')[0]; style='color:white;'><?php echo "&nbsp;<b>".$NAME." ".$VERS."</b>&nbsp;<br>&nbsp;".strftime("%Y-%m-%d %H:%M:%S", time()).""; ?>
    </td>
    <td width='40' align='center' title="Settings">
        &nbsp;<a href="javascript:alert('NOT_READY!');" ><img src='menu.png' width='30' border='0'></a>&nbsp;
    </td>
    <td align='top'>
    
        &nbsp;&nbsp;&nbsp;
        <a href='http://www.tvmaze.com/api' target='_blank'>[TVMaze.com API]</a>
        &nbsp;&nbsp;&nbsp;
        <a href='http://api.tvmaze.com/singlesearch/shows?q=castle&embed=episodes' target='_blank' title='Use this tool to find any show by name. Replace "castle" to search.'>[Search TVmaze.com by Name]</a>
        &nbsp;&nbsp;&nbsp;
        <a href='http://www.jsoneditoronline.org/' target='_blank' title='Use this tool to easily read the JSON returned from TVmaze.com'>[Online JSON Editor]</a>
        &nbsp;&nbsp;&nbsp;
        <a href='javascript:void(0);' onClick='spinnerOn();addTVMID();' title='Add this new TVMID to your tracker list. Click here to submit. Must be numeric.'>Add TVMID:</a><input id='TVMID_input' size='7' maxlength='7'>
        &nbsp;
        <br>
        <center>
            <span id='message' class='message tdchop' style='width:100%'>Click title "Unseen" to sort by unseen episodes, or sort by "NextAired" to watch cross-over shows in date order.</span>
        </center>
    
    </td>
  </tr>
</table>

<script>  
    function addTVMID() {
        // alert("ADDTVMID");
        var tvmid = 1* document.getElementById('TVMID_input').value;   // force numeric
        if ( tvmid > 0 ) {
            // alert( tvmid );
            var execute = '<?php echo basename($_SERVER["SCRIPT_NAME"]); ?>';
            var execute = execute + "?TVMID=" + tvmid + "&ADD" ;
            // alert(execute);
            spinnerOn();
            window.location = execute ;
        } else {
            alert("TVMID must be a non-zero integer representing a TVMaze.com show ID");
        }
    return(0);    
    }
</script>






<?php

## 0. Initialize


    # this is the global array that contains all of the show+user info
    $SHOWS = array(
        array("SHOW_ID", "EPNUM", "WATCHED", "JSON")
    );
        
## 1. getShows()
getShows();         #!# should this be done AFTER the update, or BEFORE and AFTER so info is fresh?
                    #!# if done before, the in-memory array must be properly updated
                    #!# if done after (again), then the page takes twice as long to load ... slow!



## 2. if (update)
    if ( isset($_REQUEST['TVMID']) and isset($_REQUEST['EPNUM']) ) {
        ##  2.1 updateShows()
        updateShows();
        ##  2.2 putShows()
        putShows();
    }

    if ( isset($_REQUEST['TVMID']) and isset($_REQUEST['ADD']) ) {
        addShow();     #!# should getShows be called again, or just update the in-memory array?
    }

    if ( isset($_REQUEST['TVMID']) and isset($_REQUEST['DEL']) ) {
        delShow();
    }

    
## 3. showShows()
showShows();



?>





    <!-- scripts that have to be loaded near the bottom of the document   -->
    

    <script>

    function callajaxFunction( funcname, content ) {
        setTimeout(function(){ document.getElementById('AJAX_LOADER').style.display='inline'; }, 10);
                    $.ajax({ 
                        type: 'GET', 
                        dataType: "text",
                        url: full_url+"?func="+funcname+"&content="+content, 
                        success: function (data) {
                            // alert(data);
                            document.getElementById('divMain').InnerHTML = data;    
                            setTimeout(function(){ document.getElementById('AJAX_LOADER').style.display='none'; }, 100);
                        }
                    });
    };

    function spinnerOff() {
        setTimeout(function(){document.getElementById('AJAX_LOADER').style.display='none';},100);
    };
    
    function spinnerOn() {
        setTimeout(function(){document.getElementById('AJAX_LOADER').style.display='inline';},10);
    };



    </script>


    <div id='AJAX_LOADER' style='display:none;position:fixed;top:0px;left:0px;background-color:silver;height:100%;width:100%;z-index:10;opacity:0.6;filter:alpha(opacity=60);'>
        <center><img src='http://hrx.dexagon.com/graphics/big-ajax-loader.gif' border=0 vspace='20%'></center>
    </div>









</body>
</html>








<?php
/* * * * * * * * * * * * * * * * * */
/* PHP FUNCTIONS                   */
/* * * * * * * * * * * * * * * * * */


    function getEpisodesDetails($TVMID, $epWatched ) {
    
        global $SHOWS;
    
        $E_unseen = 0;
        $E_unaired = 0 ;
        $E_total = 0;
        $E_NEXT = "" ;                  # store each unseen episode, because the last one "unseen" is the first (ie. next) one to be seen
        $E_Watched_ptr = 0 ;
        
        $tmp_url = "http://api.tvmaze.com/shows/" . $TVMID . "?embed=episodes" ;    
#        echo $tmp_url;
        
        $ch = curl_init();              # create curl resource
        curl_setopt($ch, CURLOPT_URL, $tmp_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);        # return the transfer as a string
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    # accept any server(peer) certificate
        $output = curl_exec($ch);       # $output contains the output string
        curl_close($ch);                # close curl resource to free up system resources        

#        echo "<b>".$output."</b><br>";
        $json = json_decode($output);
        
#        echo "<hr>";
#        echo "<br>ID: " .  $json->{"id"} ; 
#        echo "<br>Name: " .  $json->{"name"} ; 
#        echo "<br>Summary: " .  $json->{"summary"} ;
        $E_total = count( $json->{"_embedded"}->{"episodes"} );
#        echo "<br>Episodes:" . $E_total ;

        # Go through the episodes backwards, so we can BREAK out of the loop afer going back to the last episode seen.
        # This works well for shows that are being actively followed, and there are only a few new episodes at a time.
        # But it fails for long-running series that are being started near their beginning 
        for ( $ptr=$E_total-1; $ptr>=0; $ptr-- ) {
        
            $E_SSSEEE = "S" . sprintf("%02d",$json->{"_embedded"}->{"episodes"}[$ptr]->{"season"}) . "E" . sprintf("%02d",$json->{"_embedded"}->{"episodes"}[$ptr]->{"number"}) ;
            $E_aired = $json->{"_embedded"}->{"episodes"}[$ptr]->{"airdate"} ;

#            echo "<br>***: Ep_ID:" . sprintf("%07d", $json->{"_embedded"}->{"episodes"}[$ptr]->{"id"}) ;##
#            echo "&nbsp;&nbsp; Ep_number:" . $E_SSSEEE ;
#            echo "&nbsp;&nbsp; Ep_aired:" . $E_aired ;
#            echo "&nbsp;&nbsp; Ep_Name:" . $json->{"_embedded"}->{"episodes"}[$ptr]->{"name"} ;    
#            echo "&nbsp;&nbsp; Ep_array" . $ptr;
            
            # check to see if it aired yet or not
            if ( ( $E_aired>=date("Y-m-d")) or ($E_aired=="") ) {
                $E_unaired++;
            }

            # check to see if it is newer than what has been watched
            if ( ($E_SSSEEE>$epWatched) and ( $E_aired<date("Y-m-d")) and ($E_aired<>"") ) {
                $E_unseen++;
                $E_NEXT = $E_SSSEEE ;
            } 

            # If this is *IS* the one that was watched, store that infor in the global $SHOWS array
            if ( ($E_SSSEEE===$epWatched) and ($epWatched<>"") ) {
                $E_Watched_ptr = $ptr;
                break;
            } 

            # Break out for older (earlier) episodes, to save time 
            # (because we are running backwards and there is no need to go back any farther!)
#            if ( ($E_SSSEEE<$epWatched) ) {
#                break;
#            }
        }
        
        return array ($output, $E_total, $E_unseen, $E_unaired, $E_NEXT, $E_Watched_ptr) ; 

    }
/* * * * * * * * * * * * * * * * * */
    function getShows(){

        global $SHOWS;                  ## make sure we use the global array for all of the info
        $SHOWS_ptr = 1;                 ## fill array from the SECOND row (first is titles)
        global $FILE ;                  ## already defined file name (matches this program name)
        
        $myfile = fopen($FILE, "r") or die("Unable to open file!");
        // Read one line until end-of-file
        while(!feof($myfile)) {
            $curLine = fgets($myfile) ;
            
            if ( preg_match("/^[1-9]/",$curLine) ) {      # only if it starts with a valid TVMID
                # echo "<br>[".$curLine . "]&nbsp;&nbsp;&nbsp;&nbsp;";
                $temparray = explode(",", $curLine);
                $SHOWS[$SHOWS_ptr][0] = $temparray[0] ; 
                $SHOWS[$SHOWS_ptr][1] = $temparray[1] ; 
                $SHOWS[$SHOWS_ptr][2] = $temparray[2] ;
                
                # call the subroutine that finds the details about the current and next episodes
                ## but only call it if this is a regular show, and not flagged as [DONE] 
                if (  ( $SHOWS[$SHOWS_ptr][2] != "[DONE]" )  )  {
                    list ($JSONTV, $E_total, $E_unseen, $E_unaired, $E_NEXT, $E_Watched_ptr)
                        = getEpisodesDetails($SHOWS[$SHOWS_ptr][0], $SHOWS[$SHOWS_ptr][1])  ;
                    $SHOWS[$SHOWS_ptr][3] = $JSONTV;
                    $SHOWS[$SHOWS_ptr][4] = $E_total; 
                    $SHOWS[$SHOWS_ptr][5] = $E_unseen; 
                    $SHOWS[$SHOWS_ptr][6] = $E_unaired; 
                    $SHOWS[$SHOWS_ptr][7] = $E_NEXT; 
                    $SHOWS[$SHOWS_ptr][8] = $E_Watched_ptr;
                    $SHOWS_ptr++;
                } else {
                    ## do we need to fix these parameters?
                }
            }
        }
        fclose($myfile);
            
        
        return;
    }
/* * * * * * * * * * * * * * * * * */
    function putShows(){

        global $SHOWS;    
        global $FILE;
        global $NAME, $VERS;

        #  split filename into components
        $FILE_name = substr($FILE,0,-4) ;
        $FILE_extn = substr($FILE,-4) ;
        
        $count = count($SHOWS) -1 ;

        $myfile = fopen($FILE_name.".tmp", "w") or die("Unable to open file!");
        fwrite($myfile, "# This is the data file for " . $NAME . " " . $VERS . "\n");
        fwrite($myfile, "# DO NOT MODIFY THIS FILE unless you understand the consequences!\n");
        fwrite($myfile, "# This file was written on " . gmstrftime("%Y-%m-%d_%H:%M:%S_UTC", time()) . "\n");
        fwrite($myfile, "# There are **" . $count . "** shows being tracked\n");
        fwrite($myfile, "# TVMID,EPNUM,Watched,Name\n\n");

#        echo "<br>putSHOWS(" . $count . ")<br>\n";
        for ( $ptr=1; $ptr<=$count; $ptr++) {

            $tmp  = $SHOWS[$ptr][0]."," ;
            $tmp .= $SHOWS[$ptr][1]."," ;
            $tmp .= $SHOWS[$ptr][2].",";

            $json = json_decode($SHOWS[$ptr][3]);
            
            $tmp .= '"' . $json->{"name"} . '"' . "\n";

#            echo $tmp."<br>";
            fwrite($myfile, $tmp);

        }

        fclose($myfile);


        # now clean up the versions of the file
        # move the last good current into a historical file (with datetime in the name)
        rename($FILE, $FILE_name."_".gmstrftime("%Y%m%d_%H%M%S_UTC", time()).$FILE_extn);

        # move the temp that we wrote into the main filename
        rename($FILE_name.".tmp", $FILE);
        
                

        return;
    }
/* * * * * * * * * * * * * * * * * */
    function showShows(){

        global $SHOWS;

        $count = count($SHOWS) -1 ;



#        echo "<br>showSHOWS(" . $count . ")<br>\n";
#        for ( $ptr=1; $ptr<=$count; $ptr++) {
#            echo "SHOWID:" . sprintf("%06d",$SHOWS[$ptr][0])."//";
#            echo "EP_NUM:" . $SHOWS[$ptr][1]."//";
#            echo "WATCHD:" . $SHOWS[$ptr][2]."//";
##            echo "JSONTV:" . $SHOWS[$ptr][3]."//";
#    echo "JSONTV length = " . sprintf("%09d",strlen($SHOWS[$ptr][3]))."//";
#            echo "TOTALE:" . $SHOWS[$ptr][4]."//";
#            echo "UNSEEN:" . $SHOWS[$ptr][5]."//";
#            echo "UNAIRD:" . $SHOWS[$ptr][6]."//";
#            echo "NEXTEP:" . $SHOWS[$ptr][7]."//";
#            echo "WE_PTR:" . $SHOWS[$ptr][8]."//";
#            echo "<br>\n";
#        }        


        echo <<<TableHeader
<div id='fixed_height' style='overflow:scroll;border:1px solid red;height:460px;width:999px;'>
<table cellspacing='0' cellpadding='2' border='1'  class="sortable" align='center' width='100%'>
 <thead>
  <tr style='background-color:silver;'>
    <td><b>TV Shows ($count)</b></td>
    <td width='65'><b>Stat    </b></td>
    <td width='15'><b>[-]    </b></td>
    <td width='30'><b>Eps    </b></td>
    <td width='30' class='newEps'><b>Unseen  </b></td>
    <td width='30'><b>Unaired  </b></td>
    <td width='2' class='sepEps'></td>
    <td width='30'><b>Episode </b></td>
    <td width='75'><b>Watched </b></td>
    <td width='75'><b>Aired   </b></td>
    <td width='130'><b>Title   </b></td>
    <td width='2' class='sepEps'></td>
    <td width='15'><b>NextEp</b></td>
    <td width='65'><b>NextAired</b></td>
    <td width='100'><b>NextTitle</b></td>
    
  </tr>
 </thead>
TableHeader
;

        for ( $ptr=1; $ptr<=$count; $ptr++) {
            showOneShow($ptr);
        }        
                
        echo <<<TableFooter
</table>
<br>
</div id='fixed_height'>
TableFooter
;



        return;
    }
/* * * * * * * * * * * * * * * * * */


function showOneShow($SHOWS_ptr) {

    global $SHOWS;
    
    $json = json_decode($SHOWS[$SHOWS_ptr][3]);
        
#        echo "<hr>";
#        echo "<br>ID: " .  $json->{"id"} ; 
#        echo "<br>Name: " .  $json->{"name"} ; 
#        echo "<br>Summary: " .  $json->{"summary"} ;
    $E_total       = count( $json->{"_embedded"}->{"episodes"} );
    $E_unseen      = $SHOWS[$SHOWS_ptr][5] ;
    $E_unaired     = $SHOWS[$SHOWS_ptr][6] ;
    $E_NEXT        = $SHOWS[$SHOWS_ptr][7] ;
    $E_Watched_ptr = $SHOWS[$SHOWS_ptr][8] ;

    $ep_aired = "-";
    $ep_title = "-";
    $ep_summary = "-";

    if ( $E_Watched_ptr<>0 ) {
        $ep_aired   = $json->{"_embedded"}->{"episodes"}[$E_Watched_ptr]->{"airdate"} ;
        $ep_title   = $json->{"_embedded"}->{"episodes"}[$E_Watched_ptr]->{"name"} ;
        $ep_summary = $json->{"_embedded"}->{"episodes"}[$E_Watched_ptr]->{"summary"} ; 
    }
    
    # get the correct row color
    global $rowColor ; 
    $rowColor = ( $rowColor=="rowA" ? "rowB" : "rowA" ) ;

    # Special link for TVMaze.com with all episode info
    #    <a href='http://api.tvmaze.com/shows/126?embed=episodes' target='_blank'>Find by TVM_ID</a>
    $tmp_url = "<a href='http://api.tvmaze.com/shows/" . $json->{"id"} . "?embed=episodes' target='_TVM' title='".$json->{"name"}."'>" . $json->{"name"} . "</a>";    

    echo "    <tr id=".$json->{"id"}." class='" . $rowColor . "' ";
    echo "      onMouseover=document.getElementById('".$json->{"id"}."').style.backgroundColor='yellow'; ";
    echo "       onMouseout=document.getElementById('".$json->{"id"}."').style.backgroundColor='white'; > \n";
    echo "        <td style='width:140px;'><span  style='width:140px;' class='tdchop'><b>" . $tmp_url    . "</b></span></td>\n";
    echo "        <td style='width:65px;'><span  style='width:65px;' class='tdchop'>" . $json->{"status"}  . "</span></td>\n";

    # give user the ability to delete this show from the list
    echo "        <td><a href='?TVMID=".$SHOWS[$SHOWS_ptr][0]."&DEL' title='DELETE this show: NOT READY YET' onClick='spinnerOn();' >[-]</a></td>\n";
 
    # episode count from the TVmaze.com database
    echo "        <td align='center'>" . $E_total . "</td>\n";


    # special indicator for NEWER episodes:   ?=unkwn,  0=none,   grtrthan zero=newer count
    $new =               "<td class='unkEps'>?</td>";    # default to unknown
    $new = ($E_unseen==0?"        <td class='no_Eps'>&nbsp;". $E_unseen . "&nbsp;</td>":$new);
    $new = ($E_unseen >0?"        <td class='newEps'>&nbsp;". $E_unseen . "&nbsp;</td>":$new);
    echo $new;    
    echo "        <td align='center'>" . $E_unaired   . "</td>\n";

    echo "        <td width='2' class='sepEps'>" . "" .  "</td>\n";
    echo "        <td><b>" . $SHOWS[$SHOWS_ptr][1] . "</b></td>\n";  # episode
    echo "        <td><b>" . $SHOWS[$SHOWS_ptr][2] . "</b></td>\n";  # watched
    echo "        <td><b>" . $ep_aired   . "</b></td>\n";
    echo "        <td width='130' class='tdchop'>" . $ep_title   . "</td>\n";
    echo "        <td width='2' class='sepEps'>" . "" .  "</td>\n";

    
    if ($E_unseen>0) {
        $cur = "[".$SHOWS[$SHOWS_ptr][1]."/".$ep_title."]";
        $nxt = $SHOWS[$SHOWS_ptr][7]    ;           ##$E_NEXT ;
        $air = $json->{"_embedded"}->{"episodes"}[$E_Watched_ptr+1]->{"airdate"} ;
        $nam = $json->{"_embedded"}->{"episodes"}[$E_Watched_ptr+1]->{"name"} ;

        $ttl = "Update last watched episode from " . $cur . " to " . $nxt . "." ;
        $inc = "<a href='?TVMID=".$json->{"id"}."&EPNUM=".$E_NEXT."' onClick='spinnerOn();' >[".$E_NEXT."]</a>" ;

        echo "        <td width='50' title='" . $ttl . "'>" . $inc . "</td>\n";
        echo "        <td                                >" . $air . "</td>\n";
        echo "        <td width=130' class='tdchop'      >" . $nam . "</td>\n";
    } else {
        echo "        <td>&nbsp;</td>\n" ;
        echo "        <td>&nbsp;</td>\n" ;
        echo "        <td>&nbsp;</td>\n" ;
    }
      


    echo "    </tr>\n";

    return ;

}
/* * * * * * * * * * * * * * * * * */

    function UpdateShows()   {      ## update an episode watched for the given show in the array

        ##########    Find that episode in the JSON array in the $SHOWS global array
        ##########    Update the information in the array
        ##########    (There is another function that actually does the "WRITE-to-DISK" operation)!

        global $SHOWS;
        $TVMID = $_REQUEST['TVMID'] ;
        $EPNUM = $_REQUEST['EPNUM'] ;

        $msg  = "Updating [" . $TVMID . "/" . $EPNUM . "] => " ;
        echo $msg;
        
        # Walk through the entire global array and implement the update request
        $count = count($SHOWS) -1 ;
        for ( $ptr=1; $ptr<=$count; $ptr++) {
            if ( $SHOWS[$ptr][0] == $TVMID ) {
        
                # get the details to be able to communicate with the user
                $json = json_decode($SHOWS[$ptr][3]);

                $SHOWS[$ptr][1] = $EPNUM ;
                $SHOWS[$ptr][2] = strftime("%Y-%m-%d", time()) ;        # local time here
                $SHOWS[$ptr][5]-- ;                                     # decrement the number of unseen episodes 
                $SHOWS[$ptr][8]++ ;                                     # increment the pointer into the array for output 
                echo "<b>" . $json->{'name'} . "</b> episode:<b>" . $SHOWS[$ptr][1] . "</b> watched at " . $SHOWS[$ptr][2];
                break;
           }
        }

        return;
    }
/* * * * * * * * * * * * * * * * * */
    function addShow() {            ## add a show to the global array
        global $SHOWS;
        global $FILE ;
        #  split filename into components
        $FILE_name = substr($FILE,0,-4) ;
        $FILE_extn = substr($FILE,-4) ;
        
        $count = count($SHOWS) -1 ;
        $TVMID = $_REQUEST['TVMID'] ;
        
        # copy orig to temp, open & append to temp, close temp file, rename orig to hist, rename temp to orig
        copy($FILE, $FILE_name.".tmp");
        $myfile = fopen($FILE_name.".tmp", "a") or die("Unable to open file!");   ## append only
        fwrite($myfile, $TVMID.",,,Unknown"."\n");
        fclose($myfile);


        # now clean up the versions of the file
        # move the last good current into a historical file (with datetime in the name)
        rename($FILE, $FILE_name."_".gmstrftime("%Y%m%d_%H%M%S_UTC", time()).$FILE_extn);

        # move the temp that we wrote into the main filename
        rename($FILE_name.".tmp", $FILE);
        
        echo "Added Show ". $_REQUEST['TVMID'] . "!";
    }
    
/* * * * * * * * * * * * * * * * * */
    function delShow() {            ## delete a show from the global array #!#
        global $SHOWS;
        $count = count($SHOWS) -1 ;
        $TVMID = $_REQUEST['TVMID'] ;

        echo "NOT WORKING!! Tried to delete show ". $_REQUEST['TVMID'] . "!";
        return ;

    }
/* * * * * * * * * * * * * * * * * */


?>