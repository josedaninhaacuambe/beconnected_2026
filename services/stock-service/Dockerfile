# ─── Build ────────────────────────────────────────────────────────────────────
FROM eclipse-temurin:21-jdk-alpine AS build
WORKDIR /app
COPY pom.xml ./
# Download dependências em camada separada (cache de layer Docker)
RUN apk add --no-cache maven && mvn dependency:go-offline -B

COPY src ./src
RUN mvn package -DskipTests -B

# ─── Runtime (JRE mínimo) ──────────────────────────────────────────────────────
FROM eclipse-temurin:21-jre-alpine
RUN addgroup -S beconnect && adduser -S beconnect -G beconnect
WORKDIR /app
COPY --from=build /app/target/*.jar app.jar
RUN chown beconnect:beconnect app.jar

USER beconnect
EXPOSE 8081

HEALTHCHECK --interval=20s --timeout=10s --retries=3 \
  CMD wget -qO- http://localhost:8081/api/stock/health || exit 1

ENTRYPOINT ["java", \
  "-XX:+UseContainerSupport", \
  "-XX:MaxRAMPercentage=75.0", \
  "-XX:+OptimizeStringConcat", \
  "-jar", "app.jar"]
