graph TD
    User[👤 User] --> LoginPage[🖥️ Login Page]
    LoginPage --> GoogleBtn[🔵 Google Login Button]
    GoogleBtn --> GoogleAuth[🔐 Google OAuth 2.0]
    GoogleAuth --> GoogleAPI[🌐 Google API]
    GoogleAPI --> UserInfo[📋 User Information]
    UserInfo --> AuthSystem[🔑 Authentication System]
    AuthSystem --> Database[(💾 Database)]
    Database --> UserCheck{❓ User Exists?}
    UserCheck -->|Yes| LoginSuccess[✅ Login Successful]
    UserCheck -->|No| CreateUser[➕ Create New User]
    CreateUser --> LoginSuccess
    LoginSuccess --> Dashboard[📊 User Dashboard]
    GoogleAuth --> AuthError[❌ Authentication Error]
    AuthError --> LoginPage 