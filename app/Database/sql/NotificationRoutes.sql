-- Enrutamiento de notificaciones: a qué rol le llega el aviso (push) de los
-- tickets nuevos de cada compañía. Ejecutar en TicketsDB (idempotente).

IF OBJECT_ID(N'dbo.TicketNotificationRoutes', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TicketNotificationRoutes] (
      [Id] int IDENTITY(1, 1) NOT NULL,
      [CodCompanies] varchar(5) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [RoleId] int NOT NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_TicketNotificationRoutes_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_TicketNotificationRoutes] PRIMARY KEY CLUSTERED ([Id]),
      CONSTRAINT [UQ_TicketNotificationRoutes] UNIQUE ([CodCompanies], [RoleId])
    );
END
GO
