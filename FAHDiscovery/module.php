public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Sysaps = $this->mDNSDiscoverSysAPs();

        $Values = [];

        foreach ($Sysaps as $Sysap) {
            $instanceID = $this->getsysAPInstances($Sysap['serialNumber']);

            $AddValue = [
                'IPAddress'             => $Sysap['IPv4'],
                'name'                  => $Sysap['deviceName'],
                'ModelName'             => $Sysap['modelName'],
                'ModelNumber'           => $Sysap['modelNumber'],
                'SerialNumber'          => $Sysap['serialNumber'],
                'instanceID'            => $instanceID
            ];

            $AddValue['create'] = [
                [
                    'moduleID'      => '{EE92367A-BB8B-494F-A4D2-FAD77290CCF4}',
                    'configuration' => [
                        'Serialnumber' => $Sysap['serialNumber']
                    ]
                ],
                [
                    'moduleID'      => '{6EFF1F3C-DF5F-43F7-DF44-F87EFF149566}',
                    'configuration' => [
                        'Host' => $Sysap['IPv4']
                    ]
                ]

            ];

            $Values[] = $AddValue;
        }
        $Form['actions'][0]['values'] = $Values;
        return json_encode($Form);
    }

    public function mDNSDiscoverSysAPs()
    {
        $mDNSInstanceIDs = IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}');
        $resultServiceTypes = ZC_QueryServiceType($mDNSInstanceIDs[0], '_busch-jaeger-sysap._tcp', '');
        $sysAP = [];
        foreach ($resultServiceTypes as $key => $device) {
            $sap = [];
            $deviceInfo = ZC_QueryService($mDNSInstanceIDs[0], $device['Name'], '_busch-jaeger-sysap._tcp', 'local.');
      //      $this->SendDebug('mDNS QueryService', $device['Name'] . ' ' . $device['Type'] . ' ' . $device['Domain'] . '.', 0);
      //      $this->SendDebug('mDNS QueryService Result', print_r($deviceInfo, true), 0);
            if (!empty($deviceInfo)) {
                $sap['Hostname'] = $deviceInfo[0]['Host'];
                if (empty($deviceInfo[0]['IPv4'])) { //IPv4 und IPv6 sind vertauscht
                    $sap['IPv4'] = $deviceInfo[0]['IPv6'][0];
                } else {
                    $sap['IPv4'] = $deviceInfo[0]['IPv4'][0];
               }
                $sapData = readSysApDataFromjson($sap['IPv4']);
                $sap['clouduuid'] = (string) $sapData->flags->cloudUuid;
                $sap['firmwareVersion'] = (string) $sapData->flags->version;
                $sap['hardwareVersion'] = (string) $sapData->flags->hardwareVersion;
                $sap['serialNumber'] = (string) $sapData->flags->serialNumber;
                array_push($sysaps, $sap);
            }
        }
        return $sysaps;
    }



    private function readSysApDataFromjson($ip)
    {
        $jsonData = file_get_contents('http://' . $ip . ':80/settings.json');
        if ($jsonData === false) {
            return;
        }
        $json = json_decode($jsonData);

        $modelName = (string) $json->flags->name;
        if (strpos($modelName, 'free@home System Access Point') === false) {
            return;
        }
        return $json;
    }

    private function getsysAPInstances($Serialnumber)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{EE92367A-BB8B-494F-A4D2-FAD77290CCF4}');
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'Serialnumber') == $Serialnumber) {
                return $id;
            }
        }
        return 0;
    }

    private function parseHeader(string $Data): array
    {
        $Lines = explode("\r\n", $Data);
        array_shift($Lines);
        array_pop($Lines);
        $Header = [];
        foreach ($Lines as $Line) {
            $line_array = explode(':', $Line);
            $Header[strtoupper(trim(array_shift($line_array)))] = trim(implode(':', $line_array));
        }
        return $Header;
    }
}