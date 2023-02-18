<?php

final class Core
{

    public $Config = array( );
    public $UserInfo = array
    (
        "user_id" => 0,
        "user_name" => "guest"
    );
    public $Language = array( );
    public $db = NULL;
    public $tpl = NULL;
    public $TablePre = "btm_";
    public $xxtea = NULL;
    public $License = array
    (
        "base" => "_x2007^BTM%\$~1!2"
    );

    public function InitAdodb( )
    {
        if ( $this->db )
        {
            return;
        }
        $this->TablePre = $this->Config['db']['prefix'];
        require_once( ROOT_PATH."/include/library/adodb/adodb.inc.php" );
        $ADODB_CACHE_DIR = ROOT_DIR_DATA.DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR."database".DIRECTORY_SEPARATOR;
        if ( !is_dir( $ADODB_CACHE_DIR ) )
        {
            mkdir( $ADODB_CACHE_DIR, 493 );
        }
        $ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
        if ( !$this->Config['db']['pconnect'] )
        {
            define( "ADODB_NEVER_PERSIST", TRUE );
        }
        $this->db =& adonewconnection( "mysql", "pear" );
        $this->db->debug = $this->Config['debug'];
        $this->db->PConnect( $this->Config['db']['host'], $this->Config['db']['user'], $this->Config['db']['password'], $this->Config['db']['name'] );
        $sql_version = $this->db->ServerInfo( );
        $this->db->Execute( "SET character_set_connection=utf8, character_set_results=utf8, character_set_client=binary" );
        if ( "5.0" < $sql_version['version'] )
        {
            $this->db->Execute( "SET sql_mode=''" );
        }
        $this->Config['db']['user'] = $this->Config['db']['password'] = "";
        $error_msg = $this->db->ErrorMsg( );
        if ( !empty( $error_msg ) )
        {
            exit( $error_msg );
        }
    }

    public function InitTemplate( $tpl_dir, $caching = TRUE, $cache_time = 600 )
    {
        if ( $this->tpl )
        {
            return;
        }
        require_once( ROOT_PATH."/include/library/smarty/Smarty.class.php" );
        $this->tpl = new Smarty( );
        $this->tpl->debugging = FALSE;
        $this->tpl->left_delimiter = "<!--{";
        $this->tpl->right_delimiter = "}-->";
        $this->tpl->template_dir = ROOT_DIR_DATA.DIRECTORY_SEPARATOR."template".DIRECTORY_SEPARATOR.$tpl_dir;
        $this->tpl->compile_dir = ROOT_DIR_DATA.DIRECTORY_SEPARATOR."template_c";
        $this->tpl->setCacheDir( ROOT_DIR_DATA.DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR."template".DIRECTORY_SEPARATOR );
        if ( !is_dir( $this->tpl->compile_dir ) )
        {
            mkdir( $this->tpl->compile_dir, 493 );
        }
        if ( !is_dir( $this->tpl->cache_dir ) )
        {
            mkdir( $this->tpl->cache_dir, 493 );
        }
        $this->tpl->compile_check = $this->Config['tpl_compile'] ? TRUE : FALSE;
        if ( !defined( "NOT_USE_CACHE" ) && $this->Config['tpl_cache'] )
        {
            $this->tpl->caching = TRUE;
            if ( !defined( "CACHE_PLACE" ) )
            {
                define( "CACHE_PLACE", "list" );
            }
            $this->tpl->cache_lifetime = $this->Config["tpl_cache_time_".CACHE_PLACE];
            if ( $this->tpl->cache_lifetime < 60 )
            {
                $this->tpl->caching = FALSE;
            }
        }
        $this->xbase( );
        $this->LanguageInit( );
    }

    public function CheckCache( $tpl_name, $sub_dir, $cache_only_key )
    {
        if ( TRUE !== $this->tpl->caching )
        {
            return;
        }
        $cache_only_key = md5( $cache_only_key );
        $sub_dir .= "/".substr( $cache_only_key, 0, 2 );
        $sub_dir .= "/".substr( $cache_only_key, 2, 1 );
        $this->CreateSubDir( $this->tpl->cache_dir, $sub_dir );
        $this->tpl['cache_dir'] .= $sub_dir;
        if ( $this->tpl->isCached( $tpl_name, $cache_only_key ) )
        {
            $this->tpl->display( $tpl_name, $cache_only_key );
            exit( );
        }
        return $cache_only_key;
    }

    public function ClearCache( $tpl_name, $sub_dir, $cache_only_key )
    {
        $cache_only_key = md5( $cache_only_key );
        $sub_dir .= "/".substr( $cache_only_key, 0, 2 );
        $sub_dir .= "/".substr( $cache_only_key, 2, 1 );
        $this->tpl['cache_dir'] .= $sub_dir;
        $this->tpl->clearCache( $tpl_name );
    }

    public function ClearShowCache( $hash_id )
    {
        $this->ClearCache( "show.tpl", "show", $hash_id );
    }

    public function CreateSubDir( $root, $dir )
    {
        $full_dir = $root;
        if ( !empty( $dir ) )
        {
            $_dir = explode( "/", $dir );
            foreach ( $_dir as $d )
            {
                $full_dir .= "/".$d;
                if ( !!is_dir( $full_dir ) && mkdir( $full_dir, 493 ) )
                {
                    return FALSE;
                }
            }
        }
        return $full_dir;
    }

    public function CheckDirPermission( $dir )
    {
        if ( !is_dir( $dir ) )
        {
            return FALSE;
        }
        if ( !file_put_contents( $dir."/test.tmp", "test1" ) )
        {
            return FALSE;
        }
        if ( !file_put_contents( $dir."/test.tmp", "test2" ) )
        {
            return FALSE;
        }
        return unlink( $dir."/test.tmp" );
    }

    public function BtFileSaveDir( $data, $hash )
    {
        $save_dir = date( addslashes( "Y".DIRECTORY_SEPARATOR."m".DIRECTORY_SEPARATOR."d" ), $data );
        return ROOT_DIR_DATA.DIRECTORY_SEPARATOR."torrents".DIRECTORY_SEPARATOR.$save_dir.DIRECTORY_SEPARATOR.$hash.".torrent";
    }

    public function UserNameCheck( $name )
    {
        return !preg_match( "#[\\x20-\\x2F\\x3A-\\x40\\x5B-\\x5E\\x60\\x7B-\\x7E]#", $name );
    }

    public function CryptPW( $password )
    {
        return md5( $password );
    }

    public function GetUserInfo( $user_id )
    {
        $user_id = intval( $user_id );
        if ( 0 < $user_id )
        {
            $this->UserInfo = $this->db->GetRow( "\r\n\t\t\t\tSELECT u.*, ln.node_site_url AS node_url, ln.node_name\r\n\t\t\t\tFROM {$this->TablePre}user AS u\r\n\t\t\t\tLEFT JOIN {$this->TablePre}login_node AS ln USING(node_id)\r\n\t\t\t\tWHERE user_id='{$user_id}'\r\n\t\t\t" );
            if ( TRUE === IS_PAY && 0 < $this->UserInfo['team_id'] )
            {
                $joined_team = $this->db->GetRow( "\r\n\t\t\t\t\tSELECT t.team_name, t.create_user_id, t.today_upgrade_num, t.join_condition, t.create_auditing, t.today, tu.*\r\n\t\t\t\t\tFROM {$this->TablePre}team AS t, {$this->TablePre}team_user AS tu\r\n\t\t\t\t\tWHERE t.team_id=tu.team_id AND tu.user_id='{$this->UserInfo['user_id']}' AND t.team_id='{$this->UserInfo['team_id']}'\r\n\t\t\t\t" );
                if ( $joined_team )
                {
                    if ( $this->UserInfo['user_id'] == $joined_team['create_user_id'] )
                    {
                        $joined_team['team_can_edit'] = 1;
                        $joined_team['team_can_delete'] = 1;
                        $joined_team['team_can_upgrade'] = 1;
                        $joined_team['team_can_manage_user'] = 1;
                    }
                    $joined_team['team_create_user_id'] = $joined_team['create_user_id'];
                    unset( $joined_team['user_id'] );
                    unset( $joined_team['team_id'] );
                    unset( $joined_team['create_user_id'] );
                    $this->UserInfo += $joined_team;
                }
                else
                {
                    $this->UserInfo['team_id'] = 0;
                }
            }
        }
    }

    public function KeywordTop( $force = FALSE )
    {
        if ( $this->Config['search_top_num'] <= 0 || $this->Config['search_close'] )
        {
            return "";
        }
        $keyword_cache_file = ROOT_DIR_DATA."/cache/cache_hot_keyword.php";
        if ( file_exists( $keyword_cache_file ) )
        {
            include_once( $keyword_cache_file );
        }
        if ( TRUE === $force )
        {
            $_BTM_CACHE['hot_keyword']['day'] = 0;
        }
        $current_day = date( "Ymd", TIME_NOW );
        if ( $current_day != $_BTM_CACHE['hot_keyword']['day'] )
        {
            $this->UpdateCache( "hot_keyword", $_BTM_CACHE['hot_keyword'] );
        }
        $keyword_list = "";
        if ( $_BTM_CACHE['hot_keyword']['word'] )
        {
            foreach ( $_BTM_CACHE['hot_keyword']['word'] as $key => $h_keyword )
            {
                $keyword_list .= "<a href=\"search.php?keyword=".urlencode( $h_keyword )."\">".$h_keyword."</a>";
                switch ( $_BTM_CACHE['hot_keyword']['state'][$key] )
                {
                case "new" :
                    $keyword_list .= "<span class=\"s_new\">(New)</span>";
                    break;
                case "drop" :
                    $keyword_list .= "<span class=\"s_drop\">&#8595;</span>";
                    break;
                case "rise" :
                    $keyword_list .= "<span class=\"s_rise\">&#8593;</span>";
                    break;
                default :
                    $keyword_list .= "";
                    break;
                }
            }
        }
        return $keyword_list;
    }

    public function MySetcookie( $name, $value = "", $expire = 0 )
    {
        if ( 0 != $expire )
        {
            $expires = TIME_NOW + $expire;
        }
        @setcookie( $name, $value, $expires, "/" );
    }

    public function CreateLoginVerifyKey( $user_id, $salt )
    {
        $user_id = intval( $user_id );
        $_len1 = substr( $user_id, 0, 1 ) * 3;
        $_len2 = 32 - $_len1;
        $verify_key = substr( md5( $salt.$user_id ), 0, $_len1 ).substr( md5( $_SERVER['HTTP_USER_AGENT'] ), 0, $_len2 );
        return $verify_key;
    }

    public function CheckLoginVerifyKey( $user_id, $salt )
    {
        if ( !$_COOKIE['BTM_VerifyKey'] || $_COOKIE['BTM_VerifyKey'] != $this->CreateLoginVerifyKey( $user_id, $salt ) )
        {
            $this->MySetcookie( "BTM_UserID" );
            $this->MySetcookie( "BTM_VerifyKey" );
            return FALSE;
        }
        return TRUE;
    }

    public function CnStrlen( $string )
    {
        if ( "" == $string )
        {
            return 0;
        }
        $string = preg_replace( "#[^\\x20-\\x7f]{2,3}#i", "00", $string );
        return strlen( $string );
    }

    public function CheckVerifyCode( $name, $value )
    {
        if ( !$this->Config["verify_code_".$name] )
        {
            return TRUE;
        }
        $value = trim( $value );
        if ( empty( $value ) )
        {
            if ( "ajax" == IN_SCRIPT )
            {
                $this->Notice( array(
                    "status" => "error",
                    "value" => $this->Language['common']['no_vcode']
                ), "json" );
            }
            else
            {
                $this->Notice( $this->Language['common']['no_vcode'], "back" );
            }
        }
        if ( $_COOKIE[$name] != md5( strtolower( $value ) ) )
        {
            if ( "ajax" == IN_SCRIPT )
            {
                $this->Notice( array(
                    "status" => "error",
                    "value" => $this->Language['common']['vcode_error']
                ), "json" );
            }
            else
            {
                $this->Notice( $this->Language['common']['vcode_error'], "back" );
            }
        }
    }

    public function DestructVerifyCode( $name )
    {
        if ( isset( $_COOKIE[$name] ) )
        {
            $this->MySetcookie( $name, 0 );
        }
    }

    public function CheckBadword( $str )
    {
        if ( empty( $str ) )
        {
            return $str;
        }
        if ( !file_exists( ROOT_DIR_DATA."/cache/cache_badword.php" ) )
        {
            return $str;
        }
        @include( ROOT_DIR_DATA."/cache/cache_badword.php" );
        if ( !$_BTM_CACHE['badword'] )
        {
            return $str;
        }
        if ( $_BTM_CACHE['badword']['banned'] && preg_match( $_BTM_CACHE['badword']['banned'], $str ) )
        {
            $btmaster->Notice( $this->Language['common']['word_banned'], "back" );
        }
        if ( $_BTM_CACHE['badword']['filter']['search'] )
        {
            return preg_replace( $_BTM_CACHE['badword']['filter']['search'], $_BTM_CACHE['badword']['filter']['replace'], $str );
        }
        return $str;
    }

    public function GetClientIP( )
    {
        if ( $_SERVER['HTTP_X_FORWARDED_FOR'] )
        {
            $clientip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $_comma_place = strpos( $clientip, "," );
            if ( $_comma_place )
            {
                $clientip = substr( $clientip, 0, $_comma_place );
            }
        }
        else if ( $_SERVER['HTTP_CLIENT_IP'] )
        {
            $clientip = $_SERVER['HTTP_CLIENT_IP'];
        }
        else
        {
            $clientip = $_SERVER['REMOTE_ADDR'];
        }
        if ( !preg_match( "/^(\\d{1,3}\\.){3}\\d{1,3}\$/", $clientip ) )
        {
            $clientip = "unknown";
        }
        define( "CLIENT_IP", $clientip );
    }

    public function CheckBadIP( )
    {
        if ( !is_array( $this->Config['bad_ip'] ) || !$this->Config['bad_ip'] )
        {
            return TRUE;
        }
        if ( in_array( CLIENT_IP, $this->Config['bad_ip'] ) )
        {
            $bad = TRUE;
        }
        else
        {
            $ip_str = explode( ".", CLIENT_IP );
            $client_ip_array[0] = preg_quote( $ip_str[0].".*.*.*" );
            $client_ip_array[1] = preg_quote( $ip_str[0].".".$ip_str[1].".*.*" );
            $client_ip_array[2] = preg_quote( $ip_str[0].".".$ip_str[1].".".$ip_str[2].".*" );
            if ( preg_match( "#".implode( "|", $client_ip_array )."#", implode( "||", $this->Config['bad_ip'] ) ) )
            {
                $bad = TRUE;
            }
        }
        if ( TRUE === $bad )
        {
            $this->Notice( $this->LangReplaceText( $this->Language['common']['ip_disabled'], CLIENT_IP ), "halt" );
        }
    }

    public function RandStr( $length = 4, $bound = "" )
    {
        switch ( $bound )
        {
        case "numeric" :
            $character = "0123456789";
            break;
        case "letter" :
            $character = "abcdefghijklmnopqrstuvwxyz";
            break;
        case "special" :
            $character = "abcdefghijklmnopqrstuvwxyz01234567890~!@#\$%^&*()_+=-";
            break;
        default :
            $character = "45679acefhjkmnprstwxy";
            break;
        }
        $character_length = strlen( $character );
        $str = "";
        $i = 0;
        for ( ; $i < $length; ++$i )
        {
            $num = mt_rand( 0, $character_length - 1 );
            $str .= $character[$num];
        }
        return $str;
    }

    public function GetReferrerUrl( )
    {
        $temp_url = $_REQUEST['url'];
        if ( empty( $temp_url ) )
        {
            $url = $_SERVER['HTTP_REFERER'];
        }
        else if ( $temp_url == $_SERVER['HTTP_REFERER'] )
        {
            $url = "index.php";
            if ( "admin" == TEMPLATE_SUB_DIR )
            {
                $url .= "?act=main";
            }
        }
        else
        {
            $url = $temp_url;
        }
        if ( empty( $url ) )
        {
            $url = "index.php";
            if ( "admin" == TEMPLATE_SUB_DIR )
            {
                $url .= "?act=main";
            }
        }
        return urlencode( $url );
    }

    public function TransitionFileSize( $size )
    {
        if ( $size < 1024 )
        {
            return $size."Bytes";
        }
        $size /= 1024;
        if ( 1048576 < $size )
        {
            $size = sprintf( "%.1f", $size / 1048576 )."GB";
        }
        else if ( 1024 < $size )
        {
            $size = round( $size / 1024, 1 )."MB";
        }
        else
        {
            $size = round( $size, 1 )."KB";
        }
        return $size;
    }

    public function BTMasterRmdir( $dir )
    {
        $result = FALSE;
        if ( !is_dir( $dir ) )
        {
            return $result;
        }
        $handle = opendir( $dir );
        while ( FALSE !== ( $file = readdir( $handle ) ) )
        {
            if ( $file != "." && $file != ".." )
            {
                $current_dir = $dir.DIRECTORY_SEPARATOR.$file;
                is_dir( $current_dir ) ? $this->BTMasterRmdir( $current_dir ) : unlink( $current_dir );
            }
        }
        closedir( $handle );
        $result = rmdir( $dir ) ? TRUE : FALSE;
        return $result;
    }

    public function ManagerLog( $action )
    {
        if ( TRUE !== IS_PAY )
        {
            return TRUE;
        }
        $manager_id = 0 < AdminUserID ? AdminUserID : $_SESSION['AdminUserID'];
        $this->db->Execute( "INSERT INTO {$this->TablePre}manager_log (manager_id, action, dateline, client_ip) VALUES ('{$manager_id}', '".htmlspecialchars( $action )."', '".TIME_NOW."', '".CLIENT_IP."')" );
    }

    public function Notice( $msg, $action = "", $url = "", $time = 3 )
    {
        $this->tpl->caching = FALSE;
        switch ( $action )
        {
        case "back" :
            $content = $msg."<br /><a href=\"javascript:self.history.back(1);\">".$this->Language['common']['back']."</a><meta http-equiv=\"Refresh\" content=\"".$time."; URL=javascript:self.history.back(1);\" />";
            break;
        case "goto" :
            $content = $msg."[<a href=\"".$url."\">".$this->Language['common']['continue']."</a>]<meta http-equiv=\"Refresh\" content=\"".$time."; URL=".$url."\" />";
            break;
        case "js" :
            exit( "<script type=\"text/javascript\">alert(\"".$msg."\");self.history.back(-1);</script>" );
        case "echo" :
            exit( $msg );
        case "json" :
            exit( json_encode( $msg ) );
        case "halt" :
        default :
            $content = $msg;
            break;
        }
        if ( defined( "NOT_USE_TEMPLATE" ) )
        {
            exit( $content );
        }
        $location = array(
            0 => array(
                "name" => $this->Language['common']['notice']
            )
        );
        $this->tpl->assign( array(
            "SiteTitle" => $this->Language['common']['notice']." - ",
            "Content" => $content,
            "Location" => $location
        ) );
        $template_name = "notice.tpl";
        if ( "user" == IN_PLACE )
        {
            $template_name = "user/".$template_name;
        }
        $this->tpl->display( $template_name );
        exit( );
    }

    public function hex2bin( $hexdata )
    {
        $bindata = "";
        $hexdata_length = strlen( $hexdata );
        $i = 0;
        for ( ; $i < $hexdata_length; $i += 2 )
        {
            $bindata .= chr( hexdec( substr( $hexdata, $i, 2 ) ) );
        }
        return $bindata;
    }

    public function UpdateCache( $filename, $addons = "" )
    {
        if ( !class_exists( "cache" ) )
        {
            require_once( ROOT_PATH."/include/kernel/class_cache.php" );
        }
        $cache = new cache( $this );
        $cache->write( $filename, $addons );
    }

    public function LoginNode( $node_id = 0 )
    {
        $cache_file_path = ROOT_DIR_DATA."/cache/cache_login_node.php";
        if ( file_exists( $cache_file_path ) )
        {
            include_once( $cache_file_path );
            if ( 0 < $node_id )
            {
                $_BTM_CACHE['login_node'] = $_BTM_CACHE['login_node'][$node_id];
            }
        }
        return $_BTM_CACHE['login_node'];
    }

    public function LangReplaceText( )
    {
        $numargs = func_num_args( );
        $arg_list = func_get_args( );
        $language_text = $arg_list[0];
        $i = 1;
        for ( ; $i < $numargs; ++$i )
        {
            $language_text = str_ireplace( "%s".$i, $arg_list[$i], $language_text );
        }
        return $language_text;
    }

    public function HeightlightKeyword( $text, $replace1 = "", $replace2 = "" )
    {
        if ( "" == $replace1 || "" == $text )
        {
            return $text;
        }
        $match_condition = "";
        if ( $replace1 == $replace2 || "" == $replace2 )
        {
            $match_condition = preg_quote( $replace1, "#" );
        }
        else
        {
            $match_condition = preg_quote( $replace1, "#" )."|".preg_quote( $replace2, "#" );
        }
        return preg_replace( "#(".$match_condition.")#iUs", "<span class=\"keyword\">\\1</span>", $text );
    }

    public function Multipage( $totalnum, $page, $perpage, $pageurl = "" )
    {
        if ( !class_exists( "Multipage" ) )
        {
            require_once( ROOT_PATH."/include/kernel/class_multipage_".( "ajax" == IN_SCRIPT ? "ajax" : TEMPLATE_SUB_DIR ).".php" );
        }
        $mpage = new Multipage( $this, $totalnum, $page, $perpage );
        if ( "ajax" == IN_SCRIPT )
        {
            $mpage->PageType = $pageurl;
        }
        else
        {
            $mpage->PageUrl = $pageurl;
        }
        return array(
            "offset" => $mpage->Offset,
            "totalpage" => $mpage->TotalPage,
            "page" => ( "admin" == TEMPLATE_SUB_DIR ? $mpage->PageInfo( ) : "" ).$mpage->ShowMultiPage( )
        );
    }

    public function UrlRequest( $url )
    {
        if ( function_exists( "curl_init" ) )
        {
            $ch = curl_init( );
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
            $contents = curl_exec( $ch );
            curl_close( $ch );
        }
        else
        {
            $contents = file_get_contents( $url );
        }
        return $contents;
    }

    public function xbase( )
    {
        if ( in_array( IN_SCRIPT, array( "ajax", "scrape", "vimg" ) ) )
        {
            return;
        }
        if ( !class_exists( "xxtea" ) )
        {
            require_once( ROOT_PATH."/include/kernel/class_xbtm.php" );
        }
        $this->xxtea = new xxtea( );
        $current_week = date( "yW", TIME_NOW );
        $license_pw = $this->License['base'].SITE_DOMAIN;
        $license_file = ROOT_DIR_DATA.DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR.md5( SITE_DOMAIN ).".php";
        if ( file_exists( $license_file ) )
        {
            include_once( $license_file );
            $license_key = $this->xxtea->unescape( $key, $license_pw );
            $verify_arr = explode( "||", $license_key );
            $this->License['week'] = $verify_arr[0];
            $this->License['state'] = $verify_arr[1];
        }
        if ( $current_week != $this->License['week'] )
        {
            $post_parameter = array(
                "v" => BTMASTER_VERSION,
                "c" => CURRENT_URL
            );
            $v_result = 1;
            if ( $v_result )
            {
                $v_result_arr = explode( "|", $v_result );
                $license_state = $v_result_arr[0];
                @fclose( $handle );
            }
            else
            {
                $license_state = $this->License['state'];
            }
            switch ( $license_state )
            {
            case 3 :
                $license_state = 3;
                break;
            case 4 :
                $license_state = 4;
                break;
            default :
                $license_state = 4;
            }
            $this->License['week'] = $current_week;
            $this->License['state'] = $license_state;
            $license_code = $this->xxtea->escape( $this->License['week']."||".$this->License['state'], $license_pw );
            $cache_info = "<?php\r\n";
            $cache_info .= "if(!defined('IN_BTMASTER')){@header('HTTP/1.1 404 Not Found');exit;}\r\n";
            $cache_info .= "// Last Updated: ".date( "H:i:s Y/m/d", TIME_NOW )."\r\n\r\n";
            $cache_info .= "\$key = '".$license_code."';";
            $cache_info .= "\r\n\r\n?>";
            if ( !file_put_contents( $license_file, $cache_info, LOCK_EX ) )
            {
                echo "<strong>Fatal error:</strong> authorization file can't write";
                exit( );
            }
        }
     
        define( "IS_PAY", 3 == $this->License['state'] || 4 == $this->License['state'] );
        if ( TRUE !== IS_PAY )
        {
            $this->Config['team_create'] = "close";
        }
    }

    private function LanguageInit( )
    {
        $language_file_path = $this->tpl->template_dir.DIRECTORY_SEPARATOR."language.php";
        if ( !file_exists( $language_file_path ) )
        {
            exit( "<br /><b>Fatal error</b>:  Language file <b>".$language_file_path."</b> does not exist<br />" );
        }
        include_once( $language_file_path );
        $this->Language = $lang;
        unset( $lang );
    }

}

if ( !defined( "IN_BTMASTER" ) )
{
    header( "HTTP/1.1 404 Not Found" );
    exit( );
}
?>
