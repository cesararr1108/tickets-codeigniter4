-- Roles del panel y escalamiento de tickets.
-- Ejecutar en TicketsDB (se puede ejecutar varias veces sin duplicar nada).

-- 1) Roles: Administrador (gestiona todo) y Técnico (atiende sus tickets).
--    Los nombres deben coincidir con Config\Tickets::$adminRoles y $technicianRoles.
IF NOT EXISTS (SELECT 1 FROM dbo.Roles WHERE Descripcion = N'Administrador')
    INSERT INTO dbo.Roles (Descripcion) VALUES (N'Administrador');

IF NOT EXISTS (SELECT 1 FROM dbo.Roles WHERE Descripcion = N'Técnico')
    INSERT INTO dbo.Roles (Descripcion) VALUES (N'Técnico');
GO

-- 2) Escalamientos: el técnico responsable escala el ticket al administrador
--    explicando por qué no lo puede resolver. Un escalamiento está activo
--    mientras ResolvedAt sea NULL.
IF OBJECT_ID(N'dbo.TicketEscalations', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TicketEscalations] (
      [IdEscalation] int IDENTITY(1, 1) NOT NULL,
      [IdTicket] int NOT NULL,
      [EscalatedBy] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [EscalatedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Reason] nvarchar(2000) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_TicketEscalations_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      [ResolvedAt] datetime2(0) NULL,
      [ResolvedBy] varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
      [ResolvedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [ResolutionNote] nvarchar(2000) COLLATE Modern_Spanish_CI_AS NULL,
      CONSTRAINT [PK_TicketEscalations] PRIMARY KEY CLUSTERED ([IdEscalation]),
      CONSTRAINT [FK_TicketEscalations_Ticket] FOREIGN KEY ([IdTicket])
        REFERENCES [dbo].[Tickets] ([IdTicket])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_TicketEscalations_Ticket] ON [dbo].[TicketEscalations] ([IdTicket], [ResolvedAt]);
END
GO

-- 3) Asignar el rol Administrador a tu usuario (cambia el correo):
-- UPDATE dbo.Users
--    SET RoleId = (SELECT Id FROM dbo.Roles WHERE Descripcion = N'Administrador')
--  WHERE Email = N'tu.correo@empresa.com';
