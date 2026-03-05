const path = require("path");
const HtmlWebpackPlugin = require("html-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CopyWebpackPlugin = require("copy-webpack-plugin");

module.exports = (env, argv) => {
  const isProduction = argv.mode === "production";

  return {
    entry: {
      taskpane: "./src/taskpane/taskpane.ts",
      commands: "./src/commands/commands.ts",
    },
    output: {
      path: path.resolve(__dirname, "dist"),
      filename: "[name].[contenthash].js",
      clean: true,
    },
    resolve: {
      extensions: [".ts", ".js"],
      alias: {
        "@services": path.resolve(__dirname, "src/services"),
        "@types": path.resolve(__dirname, "src/types"),
      },
    },
    module: {
      rules: [
        {
          test: /\.ts$/,
          use: "ts-loader",
          exclude: /node_modules/,
        },
        {
          test: /\.css$/,
          use: [
            isProduction ? MiniCssExtractPlugin.loader : "style-loader",
            "css-loader",
          ],
        },
      ],
    },
    plugins: [
      new HtmlWebpackPlugin({
        template: "./src/taskpane/taskpane.html",
        filename: "taskpane.html",
        chunks: ["taskpane"],
        inject: "body",
        minify: isProduction
          ? {
              collapseWhitespace: true,
              removeComments: true,
              removeRedundantAttributes: true,
            }
          : false,
      }),
      new HtmlWebpackPlugin({
        template: "./src/commands/commands.html",
        filename: "commands.html",
        chunks: ["commands"],
        inject: "body",
        minify: isProduction,
      }),
      ...(isProduction
        ? [
            new MiniCssExtractPlugin({
              filename: "[name].[contenthash].css",
            }),
          ]
        : []),
    ],
    devServer: {
      static: {
        directory: path.resolve(__dirname, "dist"),
      },
      port: 3000,
      https: true,
      headers: {
        "Access-Control-Allow-Origin": "*",
        "Content-Security-Policy":
          "default-src 'self' https://procom.example.com; " +
          "script-src 'self' https://appsforoffice.microsoft.com; " +
          "style-src 'self' 'unsafe-inline'; " +
          "img-src 'self' data:; " +
          "connect-src 'self' https://procom.example.com;",
      },
    },
    devtool: isProduction ? "source-map" : "eval-source-map",
    performance: {
      hints: isProduction ? "warning" : false,
      maxAssetSize: 512000,
      maxEntrypointSize: 512000,
    },
  };
};
