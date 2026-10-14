-- Cronómetro de trabajo por ticket: el responsable pulsa "Iniciar" al comenzar
-- y "Finalizar" al terminar (o se detiene solo al cerrar/reasignar el ticket).
-- Cada fila es una sesión; EndedAt NULL = cronómetro corriendo.
-- Ejecutar en TicketsDB (se puede ejecutar varias veces sin duplicar nada).

IF OBJECT_ID(N'dbo.TicketTimeLogs', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TicketTimeLogs] (
      [IdLog] int IDENTITY(1, 1) NOT NULL,
      [IdTicket] int NOT NULL,
      [IdUser] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [UserName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [StartedAt] datetime2(0) CONSTRAINT [DF_TicketTimeLogs_StartedAt] DEFAULT sysutcdatetime() NOT NULL,
      [EndedAt] datetime2(0) NULL,
      CONSTRAINT [PK_TicketTimeLogs] PRIMARY KEY CLUSTERED ([IdLog]),
      CONSTRAINT [FK_TicketTimeLogs_Ticket] FOREIGN KEY ([IdTicket])
        REFERENCES [dbo].[Tickets] ([IdTicket])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_TicketTimeLogs_Ticket] ON [dbo].[TicketTimeLogs] ([IdTicket], [EndedAt]);
    CREATE NONCLUSTERED INDEX [IX_TicketTimeLogs_User] ON [dbo].[TicketTimeLogs] ([IdUser], [StartedAt]);
END
GO
