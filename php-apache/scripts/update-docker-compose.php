<?php

require_once 'Spyc.php';

$options = getopt('c:e:a:yn', ['compose-file:', 'env-file:', 'acme-path', 'always-yes', 'always-no']);

$composeFilePath = ($options['c'] ?? $options['compose-file'] ?? null );
$envFilePath = ($options['e'] ?? $options['env-file'] ?? null );
$acmeFilePath = ($options['a'] ?? $options['acme-path'] ?? null );
$alwaysYes = (isset($options['y']) || isset($options['always-yes']));
$alwaysNo = (isset($options['n']) || isset($options['always-no']));
$usage = "Usage php ./update-docker-compose.php -c /path/to/docker-compose.yml -e /path/to/docker/.env [-a /path/to/acme.json|--acme-path=/path/to/acme.json] [-y|--always-yes] [-n|--always-no]\n";
if (!$composeFilePath || !$envFilePath) {
	echo $usage;
	exit();
}

if ($alwaysYes && $alwaysNo) {
	echo "You can't specify -y|--always-yes and -n|--always-no at the same time.\n";
	exit();
}

// This is BASIC. Won't work if the syntaxes are too twisted.
function parseEnv($filePath)
{
	$values = [];

	$content = file_get_contents($filePath);
	$rows = explode("\n", $content);
	foreach ($rows as $row) {
		if (preg_match('/^\s*#/', $row) || preg_match('/^\s*$/', $row))
			continue;
		$pair = preg_split('/\s*=\s*/', $row, 2);

		if (!isset($pair[1]) || !strlen(rtrim($pair[1]))) {
			$values[$pair[0]] = '';
			continue;
		}
		$key = $pair[0];
		$value = rtrim($pair[1]);
		if ($value[0] === '\'') {
			$pos = strpos($value, '\'', 1);
			if ($pos === false) {
				echo "Bad quotes for $key\n";
				continue;
			}
			$value = substr($value, 1, $pos - 1);
		}
		else if ($value[0] === '"') {
			$pos = strpos($value, '"', 1);
			if ($pos === false) {
				echo "Bad quotes for $key\n";
				continue;
			}
			$value = substr($value, 1, $pos - 1);
		}
		$values[$key] = $value;
	}

	return $values;
}

function getLabelIndex($labels, $key)
{
	foreach ($labels as $index => $label) {
		if (substr($label, 0, strlen($key) + 1) === $key . '=')
			return $index;
	}

	return -1;
}

function askConfirmation($question, $default = false)
{
	$handle = fopen ('php://stdin','r');
	echo $question . ($default ? '(Y/n)' : '(y/N)') ."\n";
	$answer = strtolower(trim(fgets($handle)));
	if (empty($answer))
		$answer = ($default ? 'y' : 'n');
	while (!in_array($answer, ['y', 'yes', 'yeah', 'n', 'no', 'nope']))
	{
		echo "Invalid answer, try again.\n";
		$answer = strtolower(trim(fgets($handle)));
		if (empty($answer))
			$answer = ($default ? 'y' : 'n');
	}

	return in_array($answer, ['y', 'yes', 'yeah']);
}

function extractTlsDomainsFromLabels(&$labels)
{
	$result = [];

	foreach ($labels as $key => $label) {
		if (preg_match('/^traefik\\.http\\.routers\\.php-http\\.tls\\.domains\\\\\[\\d+\\\\\]\\.main=*/', $label)) {
			$result[] = $label;
			unset($labels[$key]);
		}
	}
	$labels = array_values($labels); // Probably unnecessary in that case. Still better.

	return $result;
}

$envData = parseEnv($envFilePath);

foreach (['ALL_DOMAINS', 'SET_WEBSERVER_WITH_TRAEFIK', 'I_WANT_MAILSERVER', 'INCLUDE_TRAEFIK_CONTAINER', ] as $value)
{
	if (!isset($envData[$value]))
	{
		echo 'Missing ' . $value ." parameter in .env\n";
		exit();
	}
}

foreach (['SET_WEBSERVER_WITH_TRAEFIK', 'I_WANT_MAILSERVER', 'INCLUDE_TRAEFIK_CONTAINER'] as $value)
{
	$envData[$value] = trim(strtolower($envData[$value]));
	if ($envData[$value] === 'true' || $envData[$value] === 'false')
	{
		$envData[$value] = ($envData[$value] === 'true' ? true : false);
	}
	else {
		echo 'Invalid value for ' . $envData[$value] . ', only true or false accepted';
		exit();
	}
}

$setTraefik = $envData['SET_WEBSERVER_WITH_TRAEFIK'];
$includeMailServer = $envData['I_WANT_MAILSERVER'];
$includeTraefik = $envData['INCLUDE_TRAEFIK_CONTAINER'];
$httpServer = $envData['HTTP_SERVER'];

if ($includeTraefik && !$acmeFilePath)
{
	echo "Since you want to include traefik, the acme.json file path should be in option (so we can create it). Maybe use setup.sh?\n";
	echo $usage;
	exit();
}

if ($httpServer !== 'nginx' && $httpServer !== 'apache')
{
	echo "HTTP_SERVER can only have as value apache of nginx.\n";
	exit();
}

$composeYml = file_get_contents($composeFilePath);
$composeYml = str_replace(['[', ']'], ['\\[', '\\]'], $composeYml);
$composeFileData = Spyc::YAMLLoadString($composeYml);
$labels = $composeFileData['services']['php-http']['labels'];
$domains = explode(',', $envData['ALL_DOMAINS']);

if ($setTraefik)
{
	if (isset($composeFileData['services']['php-http']['ports']))
	{
		echo "The ports are specified for php-http. This shouldn't be specified with traefik. Removing it.\n";
		unset($composeFileData['services']['php-http']['ports']);
	}
}
else
{
	if (!isset($composeFileData['services']['php-http']['ports']))
	{
		echo "Adding port bindings for 80 and 443\n";
		$composeFileData['services']['php-http']['ports'] = ['80:80', '443:443'];
	}
	else
	{
		if (!in_array('80:80', isset($composeFileData['services']['php-http']['ports'])))
		{
			echo "Adding port binding for 80\n";
			$composeFileData['services']['php-http']['ports'] = '80:80';
		}
		if (!in_array('443:443', isset($composeFileData['services']['php-http']['ports'])))
		{
			echo "Adding port binding for 443\n";
			$composeFileData['services']['php-http']['ports'] = '443:443';
		}
	}
}

if (($labelIndex = getLabelIndex($labels, 'traefik.http.routers.php-http.rule')) >= 0)
{
	if ($setTraefik)
	{
		if (!$alwaysNo && ($alwaysYes || askConfirmation('Host rule already present. Overwrite it?')))
		{
			echo "Overwriting of the host rule.\n";
			$labels[$labelIndex] = 'traefik.http.routers.php-http.rule=' . 'Host(`' . implode('`, `', $domains) . '`)';
		}
		else
		{
			echo "Skipping the overwriting of the host rule.\n";
		}
	}
	else
	{
		echo "Removing the routing rule...\n";
		unset($labels[$labelIndex]);
		$labels = array_values($labels);
	}
}
else if ($setTraefik)
{
	echo "Adding routing rule\n";
	$labels[] = 'traefik.http.routers.php-http.rule=' . 'Host(`' . implode('`, `', $domains) . '`)';
}

$tlsDomains = extractTlsDomainsFromLabels($labels); // as an extraction, it removes them from labels

if ($setTraefik)
{
	if (count($tlsDomains) > 0)
	{
		if (!$alwaysNo && ($alwaysYes || askConfirmation('Router tls domains already present. Overwrite it?')))
		{
			echo "Overwriting of the tls domains.\n";
			$tlsDomains = [];
			foreach ($domains as $i => $domain) {
				$tlsDomains[] = 'traefik.http.routers.php-http.tls.domains\\[' . $i . '\\].main=' . $domain;
			}
		}
		else
		{
			echo "Skipping the overwriting of tls domains.\n";
		}
	}
	else
	{
		foreach ($domains as $i => $domain) {
			$tlsDomains[] = 'traefik.http.routers.php-http.tls.domains\\[' . $i . '\\].main=' . $domain;
		}
	}
}
else
{
	if (count($tlsDomains) > 0)
	{
		echo "Tls domains present. Not needed without traefik. Removing it...\n";
		$tlsDomains = [];
	}
}

foreach ([
			['traefik.http.routers.php-http.tls', 'true', 'tls'],
			['traefik.http.routers.php-http.tls.certresolver', 'letsencrypt', 'certresolver'],
			['traefik.http.services.php-http.loadbalancer.server.port', '80', 'loadbalancer port'],
			['traefik.enable', 'true', 'enabling'],
		] as $traefikRuleData) {
	if (($labelIndex = getLabelIndex($labels, $traefikRuleData[0])) >= 0)
	{
		if (!$setTraefik)
		{
			echo 'Removing the traefik '. $traefikRuleData[2] ." rule...\n";
			unset($labels[$labelIndex]);
			$labels = array_values($labels);
		}
	}
	else if ($setTraefik)
	{
		echo 'Adding the traefik '. $traefikRuleData[2] ." rule...\n";
		$labels[] = $traefikRuleData[2] . '=' . $traefikRuleData[1];
	}
}

$labels = array_merge($labels, $tlsDomains);
$composeFileData['services']['php-http']['labels'] = $labels;
if (!$includeMailServer)
{
		if (isset($composeFileData['services']['mailserver']))
		{
			unset($composeFileData['services']['mailserver']);
		}
		$composeFileData['services']['php-http']['depends_on'] = ['database'];
}
else
{
		$composeFileData['services']['php-http']['depends_on'] = ['database', 'mailserver'];
}

if (!$includeTraefik && isset($composeFileData['services']['traefik']))
{
	unset($composeFileData['services']['traefik']);
}
$resultYml = Spyc::YAMLDump($composeFileData, false, 0);
$resultYml = str_replace(['\\[', '\\]'], ['[', ']'], $resultYml);

if ($includeMailServer && !isset($composeFileData['services']['mailserver']))
{
	// it's not great to just concat it like that, but it's simple, and keeps comments. I like comments.

	$resultYml .= "\n" . '  mailserver:
    build:
      context: ./mailserver
    environment:
      EMAIL: ${EMAIL}
      EMAIL_PASSWORD: ${EMAIL_PASSWORD}
    container_name: mailserver
    # If the FQDN for your mail-server is only two labels (eg: example.com),
    # you can assign this entirely to `hostname` and remove `domainname`.
    hostname: ${MAIN_DOMAIN}
    env_file: mailserver.env
    # More information about the mail-server ports:
    # https://docker-mailserver.github.io/docker-mailserver/edge/config/security/understanding-the-ports/
    # To avoid conflicts with yaml base-60 float, DO NOT remove the quotation marks.
#   ports:
#     - "25:25"    # SMTP  (explicit TLS => STARTTLS)
#     - "143:143"  # IMAP4 (explicit TLS => STARTTLS)
#     - "465:465"  # ESMTP (implicit TLS)
#     - "587:587"  # ESMTP (explicit TLS => STARTTLS)
#     - "993:993"  # IMAP4 (implicit TLS)
    volumes:
      - ./maildata/dms/mail-data/:/var/mail/
      - ./maildata/dms/mail-state/:/var/mail-state/
      - ./maildata/dms/mail-logs/:/var/log/mail/
      - ./maildata/dms/config/:/tmp/docker-mailserver/
      - /etc/localtime:/etc/localtime:ro
      - ./certs/:/etc/letsencrypt
    restart: always
    stop_grace_period: 1m
    cap_add:
      - NET_ADMIN
    healthcheck:
      test: "ss --listening --tcp | grep -P \'LISTEN.+:smtp\' || exit 1"
      timeout: 3s
      retries: 0' . "\n";
}

if ($httpServer === 'apache')
{
	$composeFileData['services']['php-http']['build']['dockerfile'] = './docker/php-apache/Dockerfile';
	$composeFileData['services']['php-http']['volumes'] = [
		'./apachelogs:/var/log/apache2',
		'./apachelogs/auth.log:/var/log/auth.log',
		'./symfonylogs:/var/www/html/${PROJECT_FOLDER_NAME}/var/log/'
	];
}
else // nginx (already checked before that the value is correct)
{
	$composeFileData['services']['php-http']['build']['dockerfile'] = './docker/php-nginx/Dockerfile';
	$composeFileData['services']['php-http']['volumes'] = [
		'./nginxlogs/:/var/log/nginx/',
		'./symfonylogs:/var/www/html/${PROJECT_FOLDER_NAME}/var/log/'
	];

}

if ($includeTraefik)
{
	if (!isset($composeFileData['services']['traefik']))
	{
		// it's not great to just concat it like that, but it's simple, and keeps comments. I like comments.

		$resultYml .= "\n" . '  traefik:
    image: traefik:v2.9
    # command: --api.insecure=true --providers.docker
    command: --providers.docker
    ports:
      - "80:80"
#      - "8080:8080"
    network_mode: "host"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./acme.json:/acme.json
    command:
      # We are going to use the docker provider
      - "--providers.docker"
      # Only enabled containers should be exposed
      - "--providers.docker.exposedByDefault=false"
      # We want to use the http server
      #- "--api.php-http=true"
      # The entrypoints we ant to expose
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"

      - "--entrypoints.web.http.redirections.entryPoint.to=websecure"
      - "--entrypoints.web.http.redirections.entryPoint.scheme=https"
      - "--entrypoints.web.http.redirections.entrypoint.permanent=true"

      - "--certificatesresolvers.letsencrypt.acme.email=$YOUR_OWN_EMAIL"
      - "--certificatesresolvers.letsencrypt.acme.storage=acme.json"
      # used during the challenge
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"

#after testing, remove --api.insecure=true and the 8080 port.' . "\n";
  	}
  	if (!file_exists($acmeFilePath))
  	{
  		file_put_contents($acmeFilePath, '{}');
  		chmod($acmeFilePath, 0600);
  	}
}

file_put_contents($composeFilePath, $resultYml);