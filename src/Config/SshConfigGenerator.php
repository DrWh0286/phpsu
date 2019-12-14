<?php

declare(strict_types=1);

namespace PHPSu\Config;

use function count;

final class SshConfigGenerator
{
    /**
     * @param string $source
     * @param string $destination
     * @param SshConnections $sshConnections
     * @return SshConnection[]
     */
    public function findShortestPath(string $source, string $destination, SshConnections $sshConnections): array
    {
        $shortestLength = PHP_INT_MAX;
        $shortest = [];
        $connections = $this->findAllPaths($source, $destination, $sshConnections);
        //TODO: warning found multiple Connection Possibilities, Selected one of the Shortest. these are all of them: ...
//        if (count($connections) > 1) {
//        }
        foreach ($connections as $connectionPath) {
            $length = count($connectionPath);
            if ($length < $shortestLength) {
                $shortest = $connectionPath;
            }
        }
        return $shortest;
    }

    /**
     * @param string $source
     * @param string $destination
     * @param SshConnections $sshConnections
     * @return SshConnection[][]
     */
    public function findAllPaths(string $source, string $destination, SshConnections $sshConnections): array
    {
        if ($source === $destination) {
            return [[]];
        }
        $result = [];
        foreach ($sshConnections->getPossibilities($destination) as $host => $possibleConnection) {
            if ($host === '') {
                /** @var $possibleConnection SshConnection */
                return [[(clone $possibleConnection)->setFrom([])]];
            }
            if ($host === $source) {
                /** @var $possibleConnection SshConnection */
                return [[(clone $possibleConnection)->setFrom([$host])]];
            }
            $possibleSubConnections = $this->findAllPaths($source, $host, $sshConnections);
            foreach ($possibleSubConnections as $possibleSubConnection) {
                /** @var $possibleConnection SshConnection */
                $possibleSubConnection[] = (clone $possibleConnection)->setFrom([$host]);
                $result[] = $possibleSubConnection;
            }
        }
        return $result;
    }

    public function generate(SshConnections $sshConnections, array $defaultSshConfig, string $currentHost): SshConfig
    {
        $sshConfig = new SshConfig();
        foreach ($sshConnections->getAllHosts() as $host) {
            $connectionsUsedForPath = $this->findShortestPath($currentHost, $host, $sshConnections);
            foreach ($connectionsUsedForPath as $sshConnection) {
                $fromHosts = $sshConnection->getFrom();
                $host = new SshConfigHost();
                $dsn = $sshConnection->getUrl();
                $host->User = $dsn->getUser();
                $host->HostName = $dsn->getHost();
                if ($dsn->getPort() !== 22) {
                    $host->Port = $dsn->getPort();
                }
                if (isset($fromHosts[0]) && $fromHosts[0] !== $currentHost) {
                    $host->ProxyJump = $fromHosts[0];
                }
                foreach ($sshConnection->getOptions() as $key => $value) {
                    $host->{$key} = $value;
                }
                $sshConfig->{$sshConnection->getHost()} = $host;
            }
        }
        if (!empty($defaultSshConfig)) {
            $host = new SshConfigHost();
            foreach ($defaultSshConfig as $key => $value) {
                $host->{$key} = $value;
            }
            $sshConfig->{'*'} = $host;
        }
        return $sshConfig;
    }
}
