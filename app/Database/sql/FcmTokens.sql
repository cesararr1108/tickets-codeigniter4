-- Tokens de Firebase Cloud Messaging (push) de panel y widget.
-- Ejecutar solo si la tabla aún no existe en TicketsDB.

IF OBJECT_ID(N'dbo.t_fcm_tokens', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[t_fcm_tokens] (
      [Id] int IDENTITY(1, 1) NOT NULL,
      [Email] varchar(120) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Token] nvarchar(255) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Navigator] varchar(100) COLLATE Modern_Spanish_CI_AS NULL,
      [fecha_registro] datetime DEFAULT getdate() NULL,
      [Companies] varchar(30) COLLATE Modern_Spanish_CI_AS NULL,
      [Rol] varchar(50) COLLATE Modern_Spanish_CI_AS NULL,
      [branches] varchar(30) COLLATE Modern_Spanish_CI_AS NULL,
      PRIMARY KEY CLUSTERED ([Id])
    ) ON [PRIMARY];

    CREATE NONCLUSTERED INDEX [IX_t_fcm_tokens_Email] ON [dbo].[t_fcm_tokens] ([Email]);
END
GO
