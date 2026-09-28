-- Tabla del chat de cada ticket (usada por el panel y por la API).
-- Ejecutar solo si la tabla aún no existe en TicketsDB.

IF OBJECT_ID(N'dbo.TicketMessages', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TicketMessages] (
      [MessageId] int IDENTITY(1, 1) NOT NULL,
      [IdTicket] int NOT NULL,
      [SenderType] varchar(10) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [SenderName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Message] nvarchar(max) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_TicketMessages_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_TicketMessages] PRIMARY KEY CLUSTERED ([MessageId]),
      CONSTRAINT [CK_TicketMessages_SenderType] CHECK ([SenderType]='agente' OR [SenderType]='cliente'),
      CONSTRAINT [FK_TicketMessages_Ticket] FOREIGN KEY ([IdTicket])
        REFERENCES [dbo].[Tickets] ([IdTicket])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_TicketMessages_Ticket] ON [dbo].[TicketMessages] ([IdTicket], [MessageId]);
END
GO
