-- Categorías por compañía e historial de cambios de los tickets.
-- Ejecutar en TicketsDB (se puede ejecutar varias veces sin duplicar nada).

-- 1) Compañías en las que se ofrece cada categoría. Las subcategorías siguen
--    a su categoría. Una categoría sin filas aquí se ofrece en todas las compañías.
IF OBJECT_ID(N'dbo.CategoryCompanies', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[CategoryCompanies] (
      [IdCategory] int NOT NULL,
      [CodCompanies] varchar(5) COLLATE Modern_Spanish_CI_AS NOT NULL,
      CONSTRAINT [PK_CategoryCompanies] PRIMARY KEY CLUSTERED ([IdCategory], [CodCompanies]),
      CONSTRAINT [FK_CategoryCompanies_Category] FOREIGN KEY ([IdCategory])
        REFERENCES [dbo].[Category] ([IdCategory])
        ON UPDATE NO ACTION
        ON DELETE CASCADE,
      CONSTRAINT [FK_CategoryCompanies_Company] FOREIGN KEY ([CodCompanies])
        REFERENCES [dbo].[Companies] ([CodCompanies])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_CategoryCompanies_Company] ON [dbo].[CategoryCompanies] ([CodCompanies]);

    -- Las categorías que ya existen quedan en las compañías 1000 y 2000.
    INSERT INTO [dbo].[CategoryCompanies] ([IdCategory], [CodCompanies])
    SELECT cat.[IdCategory], c.[CodCompanies]
    FROM [dbo].[Category] cat
    CROSS JOIN [dbo].[Companies] c
    WHERE c.[CodCompanies] IN ('1000', '2000');
END
GO

-- 2) Historial de cambios del ticket: quién cambió qué y cuándo
--    (ej. de "Incidente/Soporte" a "Desarrollo", cierre, reapertura).
IF OBJECT_ID(N'dbo.TicketChanges', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TicketChanges] (
      [IdChange] int IDENTITY(1, 1) NOT NULL,
      [IdTicket] int NOT NULL,
      [IdUser] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [UserName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Field] varchar(40) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [OldValue] nvarchar(255) COLLATE Modern_Spanish_CI_AS NULL,
      [NewValue] nvarchar(255) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_TicketChanges_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_TicketChanges] PRIMARY KEY CLUSTERED ([IdChange]),
      CONSTRAINT [FK_TicketChanges_Ticket] FOREIGN KEY ([IdTicket])
        REFERENCES [dbo].[Tickets] ([IdTicket])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_TicketChanges_Ticket] ON [dbo].[TicketChanges] ([IdTicket], [CreatedAt]);
END
GO
