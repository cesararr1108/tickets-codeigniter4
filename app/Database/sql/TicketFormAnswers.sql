-- Respuestas de los formularios adicionales del widget (Proyecto,
-- Requerimiento, Incidente/soporte...). Una sola tabla para TODOS los
-- formularios: cada fila es un campo respondido de un ticket.
--
--   FormKey   -> formulario usado ('proyecto', 'requerimiento', 'incidente')
--   FieldKey  -> clave del campo (data-field del HTML)
--   Label     -> etiqueta tal como la vio el solicitante
--   Value     -> respuesta (las selecciones múltiples van separadas por coma)
--   SortOrder -> orden en el formulario, para mostrarlas igual en el panel
--
-- Ejecutar solo si la tabla aún no existe en TicketsDB.

IF OBJECT_ID(N'dbo.TicketFormAnswers', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TicketFormAnswers] (
      [IdAnswer] int IDENTITY(1, 1) NOT NULL,
      [IdTicket] int NOT NULL,
      [FormKey] varchar(40) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [FieldKey] varchar(60) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Label] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Value] nvarchar(max) COLLATE Modern_Spanish_CI_AS NULL,
      [SortOrder] int CONSTRAINT [DF_TicketFormAnswers_SortOrder] DEFAULT 0 NOT NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_TicketFormAnswers_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_TicketFormAnswers] PRIMARY KEY CLUSTERED ([IdAnswer]),
      CONSTRAINT [FK_TicketFormAnswers_Ticket] FOREIGN KEY ([IdTicket])
        REFERENCES [dbo].[Tickets] ([IdTicket])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_TicketFormAnswers_Ticket] ON [dbo].[TicketFormAnswers] ([IdTicket], [SortOrder]);
END
GO
