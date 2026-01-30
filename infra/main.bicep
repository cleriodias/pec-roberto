param appName string = 'PEC83'
param location string = 'Brazil South'
param appServicePlanId string = '/subscriptions/51242591-9d16-401e-91da-fd5b6083c135/resourceGroups/rg-pec-rodrigo/providers/Microsoft.Web/serverfarms/sp-rodrigo-pec'
param storageAccountName string = toLower('st${uniqueString(resourceGroup().id, appName)}')
param storageContainerName string = 'packages'

resource storage 'Microsoft.Storage/storageAccounts@2023-05-01' = {
  name: storageAccountName
  location: location
  sku: {
    name: 'Standard_LRS'
  }
  kind: 'StorageV2'
  properties: {
    allowBlobPublicAccess: false
    minimumTlsVersion: 'TLS1_2'
    supportsHttpsTrafficOnly: true
  }
}

resource storageBlobService 'Microsoft.Storage/storageAccounts/blobServices@2023-05-01' = {
  parent: storage
  name: 'default'
}

resource storageContainer 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = {
  parent: storageBlobService
  name: storageContainerName
  properties: {
    publicAccess: 'None'
  }
}

resource webApp 'Microsoft.Web/sites@2024-11-01' = {
  name: appName
  location: location
  kind: 'app,linux'
  properties: {
    serverFarmId: appServicePlanId
    httpsOnly: true
    reserved: true
    clientCertEnabled: false
    clientCertMode: 'Optional'
    siteConfig: {
      linuxFxVersion: 'PHP|8.2'
      appCommandLine: 'cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-available/default && cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-enabled/default && service nginx reload'
      alwaysOn: false
      ftpsState: 'FtpsOnly'
    }
  }
}

resource webAppConfig 'Microsoft.Web/sites/config@2024-11-01' = {
  parent: webApp
  name: 'web'
  properties: {
    linuxFxVersion: 'PHP|8.2'
    appSettings: [
      {
        name: 'WEBSITE_DOCUMENT_ROOT'
        value: '/home/site/wwwroot/public'
      }
      {
        name: 'WEBSITE_RUN_FROM_PACKAGE'
        value: '0'
      }
      {
        name: 'WEBSITES_ENABLE_APP_SERVICE_STORAGE'
        value: 'true'
      }
      {
        name: 'WEBSITES_PORT'
        value: '8080'
      }
      {
        name: 'WEBSITE_NGINX_CONFIG_PATH'
        value: '/home/site/wwwroot/nginx.conf'
      }
      {
        name: 'APP_STORAGE'
        value: '/home/site/storage'
      }
    ]
  }
}

resource webAppFtp 'Microsoft.Web/sites/basicPublishingCredentialsPolicies@2024-11-01' = {
  parent: webApp
  name: 'ftp'
  properties: {
    allow: false
  }
}

resource webAppScm 'Microsoft.Web/sites/basicPublishingCredentialsPolicies@2024-11-01' = {
  parent: webApp
  name: 'scm'
  properties: {
    allow: true
  }
}

output storageAccountName string = storage.name
output storageContainerName string = storageContainerName
