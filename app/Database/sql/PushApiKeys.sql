-- API keys de la API de notificaciones push (notificaciones-push-roma), una por
-- compañía: la API crea una llave por organización (1000, 2000, ...).
-- Tabla aparte de Companies para que la llave nunca salga por /api/companies.
-- Se administran en Panel > Notificaciones. Ejecutar en TicketsDB (idempotente).

IF OBJECT_ID(N'dbo.CompanyPushKeys', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[CompanyPushKeys] (
      [CodCompanies] varchar(5) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ApiKey] varchar(200) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [UpdatedAt] datetime2(0) CONSTRAINT [DF_CompanyPushKeys_UpdatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_CompanyPushKeys] PRIMARY KEY CLUSTERED ([CodCompanies])
    );
END
GO
