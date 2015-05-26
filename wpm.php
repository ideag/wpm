<?php

  $wpm = new WPM( $argv );

  class WPM {
    public $config = array();
    public $path = '';
    public $wp_path = '';
    public $error = false;

    public function __construct( $args ) {
      if ( !$this->load_config() ) {
        die( $this->error."\r\n" );
      }
      if ( isset($args[1]) ) {
        switch ( $args[1] ) {
          case 'install' :
            if ( !isset( $args[2] ) ) {
              $args[2] = 'all';
            }
            switch ( $args[2] ) {
              case 'all' :
                // install core
                $this->core_install();
                // config core
                $this->core_setup();
                // install plugins
                $this->plugins_install();
                // install themes
                $this->themes_install();
                // install users
                $this->users_install();
              break;
              case 'core' :
                // install core
                $this->core_install();
                // config core
                $this->core_setup();
              break;
              case 'plugins' :
                // install plugins
                $this->plugins_install();
              break;
              case 'themes' :
                // install themes
                $this->themes_install();
              break;
              case 'users' :
                // install users
                $this->users_install();
              break;
            }
          break;
          case 'server' :
            // install themes
            $this->server_install();
          break;
          default :
            die( 'Unknown command' );
          break;
        }
      }
    }

    public function load_config() {
      $this->path = __DIR__;
      $this->config = file_get_contents( $this->path.'/wpm.json' );
      if (!$this->config) {
        $this->error = 'no wpm.json file found';
        return false;
      }
      $this->config = json_decode( $this->config, true );
      if ( !is_array($this->config) ) {
        $this->error = 'error in wpm.json file';
        return false;
      }
      return true;
    }

    public function server_install() {
      $path = $this->path;
      $wp_path = $path.'/wordpress';
      switch ( $this->config['server'] ) {
        case 'nginx' :
          $file = "server {
                  server_name  www.{$this->config['site']['domain']};
                  rewrite ^ \$scheme://{$this->config['site']['domain']}\$request_uri redirect;
          }

          server {
                  server_name {$this->config['site']['domain']};
                  root {$wp_path};

                  index index.php;

                  include global/restrictions.conf;
                  include global/wordpress.conf;
          }";
          $cmd = "echo '{$file}' > /etc/nginx/sites-available/{$this->config['site']['domain']}";
          exec( $cmd );
          $cmd = "ln -s /etc/nginx/sites-available/{$this->config['site']['domain']} /etc/nginx/sites-enabled/{$this->config['site']['domain']}";
          passthru( $cmd );
          $cmd = "service nginx restart";
          passthru( $cmd );

        break;
      }
    }

    public function core_setup() {
      echo "Configuring core";
      $path = $this->path;
      $wp_path = $path.'/wordpress';
      $cmd = "wp core config --dbname={$this->config['db']['name']} --dbuser={$this->config['db']['user']} --dbpass={$this->config['db']['pass']} --path={$wp_path} --quiet";
      exec( $cmd );
      $cmd = "mv {$wp_path}/wp-config.php {$path}/wp-config.php";
      exec( $cmd );
      $cmd = "wp core install --url={$this->config['site']['domain']} --title=\"{$this->config['site']['title']}\" --admin_user={$this->config['site']['admin_user']} --admin_email={$this->config['site']['admin_email']} --admin_password={$this->config['site']['admin_password']} --path={$wp_path} --quiet";
      exec( $cmd, $output );
      echo " - OK\r\n";
    }
    public function core_install() {
      echo "Installing site {$this->config['site']['domain']}...\r\n";
      // CORE
      $repo = "https://github.com/WordPress/WordPress.git";
      $core_version = $this->git_version( $this->config['core'], $repo );
      echo " - core version: ".$core_version;
      // exec('rm wordpress -Rfd');
      $path = $this->path.'/wordpress';
      $this->git_install( $core_version, $repo, $path );
      $ignore = "wp-content";
      file_put_contents( $path.'/.gitignore', $ignore );
      echo " - OK\r\n";
    }

    public function plugins_install() {
      $path = $this->path;
      $wp_path = $path.'/wordpress';
      echo "Installing plugins:\r\n";
      foreach ( $this->config['plugins'] as $name => $version ) {
        $name_ex = explode('/', $name);
        $slug = $name_ex[1];
        exec("rm {$wp_path}/wp-content/plugins/{$slug} -Rfd");
        $repo = $name;
        if ( 'org' === $name_ex[0] ) {
          $repo = "wp-plugins/{$slug}";
        }
        $repo = "https://github.com/{$repo}.git";
        $plugin_version = $this->git_version( $version, $repo );
        if( $plugin_version ) {
          echo " - plugin {$name} version: {$plugin_version}";
          $plugin_path = $wp_path."/wp-content/plugins/{$slug}";
          $this->git_install( $plugin_version, $repo, $plugin_path );
          echo " - OK\r\n";
        } else {
          echo " - plugin {$name} version: not found - FAIL \r\n";
        }
      }
      echo "Activating plugins";
      exec('wp plugin activate --path=wordpress --all --quiet');
      echo " - OK\r\n";
    }

    public function themes_install() {
      $path = $this->path;
      $wp_path = $path.'/wordpress';
      echo "Installing themes:\r\n";
      foreach ( $this->config['themes'] as $name => $version ) {
        $name_ex = explode('/', $name);
        $slug = $name_ex[1];
        exec("rm {$wp_path}/wp-content/themes/{$slug} -Rfd");
        $repo = "https://github.com/{$name}.git";
        $method = 'git';
        if ( 'org' === $name_ex[0] ) {
          $repo = "https://themes.svn.wordpress.org/{$slug}";
          $theme_version = $this->svn_version( $version, $repo );
          $repo .= "/{$theme_version}";
          $method = "svn";
        } else if ( 'com' === $name_ex[0] ) {
          $repo = "https://wpcom-themes.svn.automattic.com/{$slug}";
          $theme_version = 'master';
          $method = "svn";
        } else {
          $theme_version = $this->git_version( $version, $repo );
          $method = 'git';
        }
        if( $theme_version ) {
          echo " - theme {$name} version: {$theme_version}";
          $theme_path = $wp_path."/wp-content/themes/{$slug}";
          switch( $method ) {
            case 'git' :
              $this->git_install( $theme_version, $repo, $theme_path );
            break;
            case 'svn' :
              $this->svn_install( $theme_version, $repo, $theme_path );
            break;
          }
          echo " - OK\r\n";
        } else {
          echo " - theme {$name} version: not found - FAIL \r\n";
        }
      }
      echo "Activating theme";
      exec("wp theme activate {$this->config['site']['theme']} --path={$wp_path} --quiet");
      echo " - OK\r\n";
    }

    public function users_install() {
      $path = $this->path;
      $wp_path = $path.'/wordpress';
      echo "Creating users:\r\n";
      exec( "wp user list --field=login --path={$wp_path} --quiet", $users );
      //var_dump($users);
      foreach ( $this->config['users'] as $name => $user ) {
        echo " - user {$name}";
        $fields = [];
        foreach ( $user as $field=>$value ) {
          $fields[$field] = "--{$field}=\"{$value}\"";
        }
        $fields = implode( ' ', $fields );
        if ( in_array($name, $users) ) {
          echo " - already exists, updating";
          exec( "wp user update {$name} {$fields} --path={$wp_path} --quiet" );
          echo " - OK\r\n";
        } else {
          exec( "wp user create {$name} {$user['user_email']} --user_pass=\"{$user['user_pass']}\"--role={$user['role']} --path={$wp_path} --quiet" );
          exec( "wp user update {$name} {$fields} --path={$wp_path} --quiet" );
          echo " - OK\r\n";
        }
      }
    }

    public function git_version( $version, $repo ) {
      $versions = $this->git_list_versions( $repo );
      $version = $this->find_version( $version, $versions );
      return $version;
    }
    public function git_list_versions( $repo ) {
      $cmd = "git ls-remote --tags {$repo}";
      exec( $cmd, $data );
      $versions = [];
      foreach ( $data as $value ) {
        $temp = $value;
        $temp = explode("\t",$temp);
        $temp = $temp[1];
        $temp = preg_replace( '/^refs\/tags\//ims', '', $temp);
        $temp = preg_replace( '/\/$/ims', '', $temp);
        $name = preg_replace( '/^v/ims', '', $temp);
        $versions[$name] = $temp;
      }
      uksort( $versions, 'version_compare' );
      return $versions;
    }
    public function git_install( $version, $repo, $path, $command="clone" ) {
      $cmd = "git {$command} {$repo} {$path} --branch {$version} --single-branch --quiet > /dev/null 2>&1";
      exec( $cmd );
    }

    public function svn_version( $version, $repo ) {
      $versions = $this->svn_list_versions( $repo );
      $version = $this->find_version( $version, $versions );
      return $version;
    }
    public function svn_list_versions( $url ) {
      $cmd = "svn list {$url}";
      exec( $cmd, $data );
      $versions = [];
      foreach ( $data as $value ) {
        $temp = $value;
        $temp = preg_replace( '/\/$/ims', '', $temp);
        $versions[$temp] = $temp;
      }
      uksort( $versions, 'version_compare' );
      return $versions;
    }
    public function svn_install( $version, $repo, $path, $command="co" ) {
      $cmd = "svn {$command} {$repo} {$path} > /dev/null 2>&1";
      exec( $cmd );
    }

    public function find_version( $version, $versions ) {
      if ( 'dev-master' == $version ) {
        return 'master';
      }
      if ( 'master' == $version ) {
        return 'master';
      }
      if ( '*' == $version ) {
        return array_pop( $versions );
      }
      $tilda = false;
      $caret = true;
      if ( 0 === strpos( $version, '~' ) ) {
        $tilda = true;
        $version = preg_replace( '/^\~/ims', '', $version);
      }
      if ( 0 === strpos( $version, '^' ) ) {
        $caret = true;
        $version = preg_replace( '/^\^/ims', '', $version);
      }
      $version = explode( '.', $version);
      $full_version = $version;
      $last_digit = array_pop( $version );
      if ( '*' === $last_digit ) {
        unset( $full_version[sizeof($full_version)-1] );
      }
      $next_version = $full_version;
      if( $tilda ) {
        ++$next_version[1];
        $i = 2;
        while( isset( $next_version[$i] ) ) {
          unset($next_version[$i]);
        }
      } else if( $caret ) {
        ++$next_version[0];
        $i = 1;
        while( isset( $next_version[$i] ) ) {
          unset($next_version[$i]);
        }
      }
      $next_version = implode( '.', $next_version );
      $version      = implode( '.', $full_version );
      $temp_version = "0";
      foreach( $versions as $number => $name ) {
        if ( ( version_compare( $version, $number, '<=' ) ) && ( version_compare( $next_version, $number, '>' ) ) ) {
          $temp_version = $number;
        }
      }
      if ( isset( $versions[$temp_version] ) ) {
        return $versions[$temp_version];
      } else {
        return false;
      }
    }

    public function curl_get($url) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
      curl_setopt($ch, CURLOPT_USERAGENT, $agent);
      $data = curl_exec($ch);
      curl_close($ch);
      return $data;
    }
  }

?>
