<?php 

namespace CacheManifest;

class CacheManifest	{

	private $metaFile = './.cache.manifest.json';
	private $files = array();
	private $version;
	private $timestamp;
	private $originalMeta;

	public function __construct($metaFile = NULL) {
		if (!is_null($metaFile)) {
            $this->metaFile = $metaFile;
		}
	}

	public function generate() {
		$this->load();
        $this->parse();
        $this->store();

        $this->response();
	}

	public function load() {
        if (is_readable($this->metaFile)) {
            $lines = file_get_contents($this->metaFile);
            $meta = json_decode($lines, true);
            $this->originalMeta = $meta;
            if (!empty($meta)) {
                foreach ($meta as $key => $value) {
                    $this->{$key} = $value;
                }
            }
        } else {
            touch($this->metaFile);
        }
    }

    public function parse() {
        $ignore_list = array( '', 'CACHE MANIFEST', );
        $section_list = array( 'CACHE:', 'NETWORK:', 'FALLBACK:', );
        $lines = file_get_contents('./cache.manifest');
        $lines = explode("\n", $lines);

        $active = '';

        foreach($lines as $line) {
            $line = trim($line);

            if (in_array($line, $ignore_list)) {
                continue;
            }


            if ($line[0] == '#') {
                $command = explode(':', trim(substr($line, 1)));
                foreach ($command as &$segment) {
                    $segment = strtolower(trim($segment));
                }

                switch($command[0]) {
                    case 'version':
                        $this->version = $command[1];
                        break;
                    case 'cache':
                        if (is_readable($command[1])) {
                            if (is_dir($command[1])) {
                                $files = $this->getFiles($command[1]);
                                foreach ($files as $file) {
                                    $this->files['CACHE'][] = $file;

                                    $filemtime = filemtime($file);
                                    if ($filemtime > strtotime($this->timestamp)) {
                                        $this->timestamp = date('Y-m-d H:i:s', $filemtime);
                                    }
                                }
                            } else {
                                $this->files['CACHE'][] = $command[1];

                                $filemtime = filemtime($command[1]);
                                if ($filemtime > strtotime($this->timestamp)) {
                                    $this->timestamp = date('Y-m-d H:i:s', $filemtime);
                                }
                            }
                        }
                        break;
                }
                continue;
            } else {
                if (in_array($line, $section_list)) {
                    $active = substr($line, 0, -1);
                    continue;
                }

                $this->files[$active][] = $line;

                if ($active == 'CACHE') {
                    $filemtime = filemtime($line);
                    if ($filemtime > strtotime($this->timestamp)) {
                        $this->timestamp = date('Y-m-d H:i:s', $filemtime);
                    }
                }
            }
        }

    }

    public function store() {
        if ($this->version != $this->originalMeta['version'] || $this->timestamp != $this->originalMeta['timestamp']) {
            $meta = array(
                'version' => $this->version,
                'timestamp' => $this->timestamp,
            );

            $meta = json_encode($meta, JSON_PRETTY_PRINT);
            file_put_contents($this->metaFile, $meta);
        }
    }

    public function getFiles($dir) {
        $files = array();
        $d = opendir($dir);
        while (false !== ($entry = readdir($d))) {
            if ($entry[0] == '.') continue;

            if (is_dir($dir.'/'.$entry)) {
                $add_files = $this->getFiles($dir.'/'.$entry);
                $files = array_merge($files, $add_files);
            } else {
                $files[] = $dir.'/'.$entry;
            }
        }
        closedir($d);
        return $files;
    }

    public function response() {
		header('Content-Type: text/cache-manifest');

        echo "CACHE MANIFEST\n";
        echo "# version: ".$this->version."-r".date('YmdHis', strtotime($this->timestamp))."\n";
        echo "\n";
        echo "CACHE:\n";

        if (!empty($this->files['CACHE'])) {
            foreach ($this->files['CACHE'] as $key => $value) {
                echo $value."\n";
            }
        }

        echo "\n";

        if (!empty($this->files['FALLBACK'])) {
            echo "FALLBACK:\n";
            foreach ($this->files['FALLBACK'] as $key => $value) {
                echo $value."\n";
            }
            echo "\n";
        }


        if (!empty($this->files['NETWORK'])) {
            echo "NETWORK:\n";
            foreach ($this->files['NETWORK'] as $key => $value) {
                echo $value."\n";
            }
            echo "\n";
        }

    }
}