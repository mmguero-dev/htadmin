<?php
include_once ("model/meta_model.php");
include_once ("tools/util.php");
/**
 * htpasswd tools for Apache Basic Auth.
 *
 * Uses crypt only!
 */
class htpasswd {
    var $fp;
    var $metafp;
    var $filename;
    var $metafilename;

    function htpasswd($htpasswdfile, $metadata_path = "") {
        @$this->fp = @$this::open_or_create ( $htpasswdfile );

		if (!is_null_or_empty_string($metadata_path)) {
			@$this->metafp = @$this::open_or_create ( $metadata_path );
			$this->metafilename = $metadata_path;
		}
		
        $this->filename = $htpasswdfile;
    }
    function user_exists($username) {
        return self::exists ( @$this->fp, $username );
    }
    function meta_exists($username) {
        return self::exists ( @$this->metafp, $username );
    }
    function meta_find_user_for_mail($email) {
        while ( ! feof ( $this->metafp ) && $meta = explode ( ":", $line = rtrim ( fgets ( $this->metafp ) ) ) ) {
            if (count ( $meta ) > 1) {
                $username = trim ( $meta [0] );
                $lemail = $meta [1];

                if ($lemail == $email) {
                    return $username;
                }
            }
        }
        return null;
    }
    function get_metadata() {
        rewind ( $this->metafp );
        $meta_model_map = array ();
        $metaarr = array ();
        while ( ! feof ( $this->metafp ) && $line = rtrim ( fgets ( $this->metafp ) ) ) {
            $metaarr = explode ( ":", $line );
            $model = new meta_model ();
            $model->user = $metaarr [0];
            if (count ( $metaarr ) > 1) {
                $model->email = $metaarr [1];
            }
            if (count ( $metaarr ) > 2) {
                $model->name = $metaarr [2];
            }
            if (count ( $metaarr ) > 3) {
                $model->mailkey = $metaarr [3];
            }

            $meta_model_map [$model->user] = $model;
        }
        return $meta_model_map;
    }
    function get_users() {
        rewind ( $this->fp );
        $users = array ();
        $i = 0;
        while ( ! feof ( $this->fp ) && trim ( $lusername = array_shift ( explode ( ":", $line = rtrim ( fgets ( $this->fp ) ) ) ) ) ) {
            $users [$i] = $lusername;
            $i ++;
        }
        return $users;
    }
    function user_add($username, $password) {
        if ($this->user_exists ( $username ))
            return false;
        fseek ( $this->fp, 0, SEEK_END );
        fwrite ( $this->fp, $username . ':' . self::htcrypt ( $password ) . "\n" );
        return true;
    }
    function meta_add(meta_model $meta_model) {
        if (self::exists ( @$this->metafp, $meta_model->user )) {
            return false;
        }
        fseek ( $this->metafp, 0, SEEK_END );
        fwrite ( $this->metafp, $meta_model->user . ':' . $meta_model->email . ':' . $meta_model->name . ':' . $meta_model->mailkey . "\n" );
        return true;
    }

    /**
     * Login check
     * first 2 characters of hash is the salt.
     *
     * @param user $username
     * @param pass $password
     * @return boolean true if password is correct!
     */
    function user_check($username, $password) {
        $err_code = self::errcode("perl_scripts/cperl perl_scripts/phtpasswd " .
            "-v -p " . escapeshellarg($password) . " -u " . escapeshellarg($username) .
            " " . escapeshellarg($this->filename),"user_check","");
        return !$err_code;
    }

    function user_delete($username) {
        return self::delete ( @$this->fp, $username, @$this->filename );
    }

    function meta_delete($username) {
        return self::delete ( @$this->metafp, $username, @$this->metafilename );
    }
    function user_update($username, $password) {
        rewind ( $this->fp );
        while ( ! feof ( $this->fp ) && trim ( $lusername = array_shift (
            explode ( ":", $line = rtrim ( fgets ( $this->fp ) ) ) ) ) ) {
            if ($lusername == $username) {
                fseek ( $this->fp, (- 1 - strlen($line)), SEEK_CUR );
                self::delete($this->fp, $username, $this->filename, false);
                file_put_contents ( $this->filename, 
                    $username . ':' . self::htcrypt ( $password ) . "\n" ,
                    FILE_APPEND | LOCK_EX);
                return true;
            }
        }
        return false;
    }
    function meta_update(meta_model $meta_model) {
        $this->meta_delete ( $meta_model->user );
        $this->meta_add ( $meta_model );
        return false;
    }
    static function write_meta_line($fp, meta_model $meta_model) {
        fwrite ( $fp, $meta_model->user . ':' . $meta_model->email . ':' . $meta_model->name . "\n" );
    }
    static function exists($fp, $username) {
        rewind ( $fp );
        while ( ! feof ( $fp ) && trim ( $lusername = array_shift ( explode ( ":", $line = rtrim ( fgets ( $fp ) ) ) ) ) ) {
            if ($lusername == $username)
                return true;
        }
        return false;
    }
    static function open_or_create($filename) {
        if (! file_exists ( $filename )) {
            return fopen ( $filename, 'w+' );
        } else {
            return fopen ( $filename, 'r+' );
        }
    }
    static function delete($fp, $username, $filename, $dorewind = true) {
        $data = '';
        $pos = ftell($fp);
        if ($dorewind) {
            rewind ( $fp );
        }
        while ( ! feof ( $fp ) && trim ( $lusername = array_shift ( explode (
            ":", $line = rtrim ( fgets ( $fp ) ) ) ) ) ) {
            if (! trim ( $line ))
                break;
            if ($lusername != $username)
                $data .= $line . "\n";
        }
        $fp = fopen ( $filename, 'r+' );
        if (!$dorewind) {
            fseek($fp, $pos);
        }
        fwrite ( $fp, rtrim ( $data ) . (trim ( $data ) ? "\n" : '') );
        ftruncate( $fp, ftell($fp));
        fclose ( $fp );
        $fp = fopen ( $filename, 'r+' );
        return true;
    }
    static function htcrypt($password) {
        $out = self::stdout("perl_scripts/cperl perl_scripts/acrypt " .
            escapeshellarg($password),"htcrypt","");
        return $out[0];
    }

    static function check_password_hash($password, $hash) {
        $err_code = self::errcode("perl_scripts/acrypt -v " .
            escapeshellarg($hash) . " " . escapeshellarg($password),"check_password_hash");
        return !$err_code;
    }

    static function errcode($cmd,$logprefix="cmd",$cwd=NULL,&$error_msg=NULL)
    {
        $tmpfname = tempnam(sys_get_temp_dir(),$logprefix.'_');

        $descriptorspec = array(
           0 => array("pipe", "r"),
           1 => array("file", $tmpfname, "a"),
           2 => array("file", $tmpfname, "a")
        );

        if($cwd === NULL)
            $cwd = sys_get_temp_dir();

        $env = array();
        $env['PATH'] = getenv('PATH');
        $env['APACHE_RUN_USER'] = getenv('APACHE_RUN_USER');
        // TODO in config
        $env['LANG'] = "en_US.UTF-8";

        while (@ ob_end_flush());

        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {

            $return_value = proc_close($process);

            if($return_value)
                if($error_msg !== NULL)
                    $error_msg = lastline($tmpfname);

            return $return_value;
        }

        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
        fwrite(STDERR, 'Could not open process'. PHP_EOL);
    }

    static function stdout($cmd,$logprefix="cmd",$cwd=NULL)
    {
        $tmpfname = tempnam(sys_get_temp_dir(),$logprefix.'_');

        $descriptorspec = array(
           0 => array("pipe", "r"),
           1 => array("pipe", "w"),
           2 => array("file", $tmpfname, "a")
        );

        if($cwd === NULL)
            $cwd = sys_get_temp_dir();

        $env = array();
        $env['PATH'] = getenv('PATH');
        $env['APACHE_RUN_USER'] = getenv('APACHE_RUN_USER');
        // TODO in config
        $env['LANG'] = "en_US.UTF-8";

        while (@ ob_end_flush());

        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {

            $out = array ();
            $fd2 = $pipes[1];
            $i = 0;

            while (($line = fgets($fd2)) !== false) {
                $out [$i] = dropn($line); // dropping LF
                $i ++;
            }

            fclose($fd2);

            $return_value = proc_close($process);

            if(!$return_value)
                return $out;
        }

        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
        fwrite(STDERR, 'Could not open process'. PHP_EOL);
    }

}


?>
