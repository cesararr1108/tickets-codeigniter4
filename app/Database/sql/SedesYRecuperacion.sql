-- Sedes adicionales por usuario y recuperación de contraseña.
-- Ejecutar en TicketsDB (se puede ejecutar varias veces sin duplicar nada).

-- 1) Sedes adicionales: además de Users.CodBranches (sede principal), un usuario
--    puede tener más sedes. Los avisos push de un ticket llegan a quienes tengan
--    asignada la sede del ticket (principal o adicional).
IF OBJECT_ID(N'dbo.UserBranches', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[UserBranches] (
      [IdUser] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [CodBranches] varchar(5) COLLATE Modern_Spanish_CI_AS NOT NULL,
      CONSTRAINT [PK_UserBranches] PRIMARY KEY CLUSTERED ([IdUser], [CodBranches])
    );
    CREATE NONCLUSTERED INDEX [IX_UserBranches_Branch] ON [dbo].[UserBranches] ([CodBranches]);
END
GO

-- 2) Recuperación de contraseña: se guarda solo el hash (SHA-256) del enlace enviado por correo.
IF OBJECT_ID(N'dbo.PasswordResets', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[PasswordResets] (
      [Id] int IDENTITY(1, 1) NOT NULL,
      [IdUser] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [TokenHash] char(64) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ExpiresAt] datetime2(0) NOT NULL,
      [UsedAt] datetime2(0) NULL,
      [RequestIp] varchar(45) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_PasswordResets_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_PasswordResets] PRIMARY KEY CLUSTERED ([Id])
    );
    CREATE NONCLUSTERED INDEX [IX_PasswordResets_Token] ON [dbo].[PasswordResets] ([TokenHash]);
    CREATE NONCLUSTERED INDEX [IX_PasswordResets_User] ON [dbo].[PasswordResets] ([IdUser], [CreatedAt]);
END
GO
