//
//  LevelListView.swift
//  ElectricSim
//
//  დონეების სია პროგრესით.
//

import SwiftUI

struct LevelListView: View {
    @EnvironmentObject var game: GameState

    var body: some View {
        List {
            if let err = game.loadError {
                Section {
                    Label(err, systemImage: "exclamationmark.triangle")
                        .foregroundStyle(.red)
                }
            }

            Section {
                ForEach(game.levels) { level in
                    let unlocked = game.isUnlocked(level)
                    NavigationLink {
                        WorkbenchView(level: level)
                    } label: {
                        LevelRow(level: level,
                                 completed: game.isCompleted(level),
                                 unlocked: unlocked)
                    }
                    .disabled(!unlocked)
                }
            } header: {
                Text("დონეები")
            } footer: {
                Text("ააწყვე ფარი, დააკავშირე სადენები და ჩართე ძაბვა. შეცდომებზე მიიღებ ახსნას ქართულად.")
            }

            Section {
                Button(role: .destructive) {
                    game.resetProgress()
                } label: {
                    Label("პროგრესის განულება", systemImage: "arrow.counterclockwise")
                }
            }
        }
        .navigationTitle("ელექტრიკის სიმულატორი")
        .navigationBarTitleDisplayMode(.inline)
    }
}

private struct LevelRow: View {
    let level: Level
    let completed: Bool
    let unlocked: Bool

    var body: some View {
        HStack(spacing: 12) {
            ZStack {
                Circle()
                    .fill(completed ? Color.green.opacity(0.2) : Color.yellow.opacity(0.15))
                    .frame(width: 40, height: 40)
                Image(systemName: completed ? "checkmark" : (unlocked ? "bolt.fill" : "lock.fill"))
                    .foregroundStyle(completed ? .green : (unlocked ? .yellow : .secondary))
            }
            VStack(alignment: .leading, spacing: 2) {
                Text(level.title).font(.headline)
                Text(level.brief)
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            }
        }
        .padding(.vertical, 4)
    }
}
