# ipv6_enable
**SIDN IPv6 enable app for Plesk, PowerDNS, etc..**

*The scripts checks if the basic DNS records for the VM are correct before you begin to setup.
After confirmation the script configures the Plesk IP setup, making the new IPv6 a shared IP before adding that IP to the existing domains.
When the IPv6 is setup, the script passes the domain list to the DNS plugin which adds the correct DNS records to the DNS server.
The DNS plugin looks for existing A records and adds a AAAA record where it is needed.
Also subdomains are checked and records added to it if needed.*


## Requirements
### Target server
- The target Linux VM has to have an IPv6 address setup in the network configuration
- There should be root access to the VM via a public key or by hop/tunnel server (also via a public key)

### Application server
- PHP 7.0+
- PHP SSH2 module
- PHP shell_exec enabled
- PHP max_execution_time 600+ seconds
- CLI access to the target VM
- API access to the local PowerDNS setup (or other DNS applications)

## Setup
### Basic setup
- Greate a hosting location that passes the minimum requirements as described above. A webserver is only needed for the ipchecker script as the main script is converted to commandline for execution time purposes.
- Copy the files of this project to the created location
- Create a key pair and copy these to the directory 'keys' (keypair should be without password)
- copy config.example.php to config.php and fill out the blanks

### Plugins
Plugins can be created for other systems than a Plesk/PowerDNS combinations. These can be placed in the directories 'plugins/cp' and 'plugins/dns'.
An array of domains should be produced by the CP plugin to pass the domains to the DNS plugin.
This should also inlude all aliases and addon domains, but you can probably leave out subdomains.
Please use the following format for this:
$domains = Array
(
    [0] => domain.nl
    [1] => domain.com
    [2] => subdomain.domain.eu
)

### Limitations
- Plesk is very slow and thus time consuming with most of its actions. This makes that the max_execution_time of the application server is very important for the amount of actions that can be done. Give the application server as much time as you can give it.
- End-of-life plesk versions are not supported. As of writing this is Plesk 17+
- The ipChecker.php script may not give correct percentages when the examined server cannot handle as much requests and starts outputting errors instead of website code. Because ofcourse the simularity between a 503 error from one IP and html code from another IP is close to 0%.

## Usage
- Start the script from the commandline as ./start.php or php start.php. In the first case, make start.php executable (chmod +x start.php)
- The ipChecker.php script is not commandline ready and must be executed in the browser.