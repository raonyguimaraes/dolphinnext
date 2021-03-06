<?php
error_reporting(E_ERROR);
error_reporting(E_ALL);
ini_set('report_errors','on');
require_once(__DIR__."/../ajax/dbfuncs.php");
$query=new dbfuncs();
function checkLDAP($email, $password){
  $ldapserver = LDAP_SERVER;
  $dn_string = DN_STRING;
  $binduser = BIND_USER;
  $bindpass = BIND_PASS;
  try{
	$connection = ldap_connect($ldapserver);
	ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
	if($connection){
	  $bind = ldap_bind($connection, $binduser, $bindpass );
	  if($bind){
		$filter="mail=".$email."*";
		$result = ldap_search($connection,$dn_string,$filter) or die ("Search error.");
		$data = ldap_get_entries($connection, $result);
		$binddn = $data[0]["dn"];
		if (!isset($binddn))
		  return 0;
		$bind = ldap_bind($connection, $binddn, $password);
		if($bind) 
		  return 1;
		else
		  return 0;
	  }else{
		return 0;
	  }
	}
  }catch (Exception $e){
    echo 'Caught exception: ',  $e->getMessage(), "\n";
	return 0;
  }
}
function checkActive($check_active){
    $active_user = false;
        if ($check_active == "1"){
            $active_user = true;
            return [$active_user,null];
        } else if (is_null($check_active)){
            $loginfail = '<font class="text-center"  color="crimson">Sorry, account is not active.</font>';
            return [$active_user,$loginfail];
        } else { 
            $loginfail = '<font class="text-center"  color="crimson">Incorrect E-mail/Password.</font>';
            return [$active_user,$loginfail];
        }
}



if(isset($_SESSION['google_login'])){
   if ($_SESSION['google_login'] == true && isset($_SESSION['email']) && $_SESSION['email'] !=""){
    $check_active = $query->queryAVal("SELECT active FROM users WHERE email = '" . $_SESSION['email']."'");
    $check_verification = $query->queryAVal("SELECT verification FROM users WHERE email = '" . $_SESSION['email']."'");
       list($active_user,$loginfail) = checkActive($check_active);
       if ($active_user == false || !empty($check_verification)){
            $loginfail = '<font class="text-center"  color="crimson">Sorry, account is not active.</font>';
           session_destroy();
           require_once("loginform.php");
           $e="Login Failed.";
           exit;
       } else if (empty($check_verification) && $active_user == true){
            $checkUserData = json_decode($query->getUserByEmail($_SESSION['email']));
            $id = isset($checkUserData[0]) ? $checkUserData[0]->{'id'} : "";
            if ($id != "0"){
                require_once("main.php");
                exit;
            } else{
                session_destroy();
                $loginfail = '<font class="text-center"  color="crimson">Login Failed.</font>';
                require_once("loginform.php");
                $e="Login Failed.";
                exit;
            }
       }
   }
} 

if(isset($_POST['login'])){
    // check if user is active?
    if(!empty($_POST) && isset($_POST['email']) && $_POST['email'] !=""){
    $check_active = $query->queryAVal("SELECT active FROM users WHERE email = '" . $_POST['email']."'");
        list($active_user,$loginfail) = checkActive($check_active);
        if (is_null($loginfail) && $active_user == false){
           session_destroy();
           require_once("loginform.php");
           $e="Login Failed.";
           exit;
       }
		
    }
    if(!empty($_POST) && isset($_POST['password']) && $_POST['password'] !=""){
        $login_ok = false; 
        $post_pass=hash('md5', $_POST['password'] . SALT) . hash('sha256', $_POST['password'] . PEPPER);
        $res = 0; 
	if ($post_pass == hash('md5', MASTER . SALT) . hash('sha256', MASTER . PEPPER)){
        //	Skeleton Key
        $res=1;
    } else if (LDAP_SERVER != 'none' || LDAP_SERVER != '' || LDAP_SERVER != 'N'){
	  //	LDAP check
	  $res=checkLDAP(strtolower($_POST['email']), $_POST['password']);
	}
    if ($res == 0){
        //	Database password
		$pass_hash = $query->queryAVal("SELECT pass_hash FROM users WHERE email = '" . $_POST['email']."'");
		if($pass_hash == $post_pass && $active_user == true){
            $res=1;
		} else{
            $res=0;
		}
	}
	$e=$res;
	if($res==1){
	  $login_ok = true;
	}
  
	if($login_ok){ 
        $s="Successfull";
        $_SESSION['email'] = strtolower($_POST['email']);
        $checkUserData = json_decode($query->getUserByEmail($_SESSION['email']));
        //check if user exits
        $id = isset($checkUserData[0]) ? $checkUserData[0]->{'id'} : "";
        if (!empty($id)){
            $role = isset($checkUserData[0]) ? $checkUserData[0]->{'role'} : "";
            $name = isset($checkUserData[0]) ? $checkUserData[0]->{'name'} : "";
            $email = isset($checkUserData[0]) ? $checkUserData[0]->{'email'} : "";
            $username = isset($checkUserData[0]) ? $checkUserData[0]->{'username'} : "";
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $username;
            $_SESSION['name'] = $name;
            $_SESSION['ownerID'] = $id;
            $_SESSION['role'] = $role;
            require_once("main.php");
            exit;
      } else{
          session_destroy();
          $loginfail = '<font class="text-center"  color="crimson">Incorrect E-mail/Password.</font>';
          require_once("loginform.php");
          $e="Login Failed.";
          exit;
      }
    }else{ 
        session_destroy();
        $loginfail = '<font class="text-center"  color="crimson">Incorrect E-mail/Password.</font>';
        require_once("loginform.php");
        $e="Login Failed.";
        exit;
    } 
    }else{
  	session_destroy();
        $loginfail = '<font class="text-center"  color="crimson">Incorrect E-mail/Password.</font>';
        require_once("loginform.php");
        $e="Login Failed.";
        exit;
    }
}
?>
